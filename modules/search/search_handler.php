<?php
/**
 * JDM Kenya - Global Search Handler
 *
 * Handles AJAX search requests for users, GBS groups, and other entities.
 * Returns JSON results for autocomplete and quick navigation.
 *
 * Input:   GET['q'] (search query string)
 * Output:  JSON array of search results (id, title, description, type, url, avatar, metadata)
 *
 * Security: Requires user session. Only returns data user is permitted to see.
 */

require_once dirname(__FILE__) . '/../../core/db_connect.php';

// --- Set JSON Content-Type ---
header('Content-Type: application/json');

// --- Validate and Sanitize Query ---
$query = trim($_GET['q'] ?? '');
if (strlen($query) < 2) {
    // Require at least 2 characters for search
    echo json_encode([]);
    exit;
}

$results = [];
$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['user_role'] ?? '';

function searchColumnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function searchTableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    $resourcesHasDescription = searchColumnExists($pdo, 'resources', 'description');
    $resourcesHasGbsId = searchColumnExists($pdo, 'resources', 'gbs_id');
    $announcementsHasGbsId = searchColumnExists($pdo, 'announcements', 'gbs_id');
    $announcementsDateColumn = searchColumnExists($pdo, 'announcements', 'date_created') ? 'date_created' : 'date_posted';

    // --- User Search ---
    // Find users by name, email, or WhatsApp phone. JDM Leaders see all roles.
    $stmt = $pdo->prepare("
        SELECT 
            id,
            name as title,
            email as description,
            role as metadata,
            'users' as type,
            CONCAT('view_member.php?id=', id) as url,
            pfp_path as avatar
        FROM users 
        WHERE (name LIKE ? OR email LIKE ? OR whatsapp_phone LIKE ?)
        AND (role != 'super_admin' OR ? = 1)
        ORDER BY 
            CASE 
                WHEN name LIKE ? THEN 1
                WHEN email LIKE ? THEN 2
                ELSE 3
            END,
            name ASC
        LIMIT 5
    ");
    $searchPattern = "%{$query}%";
    $stmt->execute([
        $searchPattern, $searchPattern, $searchPattern,
        $user_role === 'super_admin' ? 1 : 0,
        $query . '%', $query . '%'
    ]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalize user metadata and avatar
    foreach ($users as &$user) {
        $user['metadata'] = ucfirst($user['metadata']); // Capitalize role
        // $user['avatar'] is already set if present
    }
    $results = array_merge($results, $users);

    // --- GBS Group Search ---
    // Members see their own groups; JDM Leader can search every GBS group.
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM gbs_members WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $has_gbs_access = $stmt->fetchColumn() > 0;

    if ($has_gbs_access || $user_role === 'super_admin') {
        if ($user_role === 'super_admin') {
            $stmt = $pdo->prepare("
                SELECT 
                    g.id,
                    g.name as title,
                    g.slogan as description,
                    g.leader_id,
                    '' as metadata,
                    'groups' as type,
                    CONCAT('gbs_group.php?id=', g.id) as url,
                    g.pfp_path as avatar
                FROM gbs_groups g
                WHERE g.name LIKE ? OR g.slogan LIKE ?
                ORDER BY g.name ASC
                LIMIT 3
            ");
            $stmt->execute([$searchPattern, $searchPattern]);
        } else {
            $stmt = $pdo->prepare("
                SELECT 
                    g.id,
                    g.name as title,
                    g.slogan as description,
                    g.leader_id,
                    '' as metadata,
                    'groups' as type,
                    CONCAT('gbs_group.php?id=', g.id) as url,
                    g.pfp_path as avatar
                FROM gbs_groups g
                JOIN gbs_members gm ON g.id = gm.gbs_id
                WHERE gm.user_id = ?
                AND (g.name LIKE ? OR g.slogan LIKE ?)
                ORDER BY g.name ASC
                LIMIT 3
            ");
            $stmt->execute([$user_id, $searchPattern, $searchPattern]);
        }
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format group metadata
        foreach ($groups as &$group) {
            // Get leader name
            $leaderStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $leaderStmt->execute([$group['leader_id']]);
            $leaderName = $leaderStmt->fetchColumn();
            $group['metadata'] = "Leader: " . ($leaderName ?: 'Unknown');
            unset($group['leader_id']);
            
            if ($group['avatar']) {
                $group['avatar'] = $group['avatar'];
            }
        }
        $results = array_merge($results, $groups);
    }

    // Search global resources. Some installs keep GBS resources in a separate table.
    $resourceDescriptionSelect = $resourcesHasDescription ? 'r.description' : "''";
    $resourceWhere = $resourcesHasDescription
        ? '(r.title LIKE ? OR r.description LIKE ?)'
        : 'r.title LIKE ?';
    $resourceUrlSelect = $resourcesHasGbsId
        ? "CASE WHEN r.gbs_id IS NOT NULL THEN CONCAT('gbs_group.php?id=', r.gbs_id) ELSE 'resources.php' END"
        : "'resources.php'";
    $resourceAccessSql = $resourcesHasGbsId
        ? 'AND (r.gbs_id IS NULL OR r.gbs_id IN (SELECT gbs_id FROM gbs_members WHERE user_id = ?))'
        : '';
    $stmt = $pdo->prepare("
        SELECT
            r.id,
            r.title,
            {$resourceDescriptionSelect} as description,
            r.file_type as metadata,
            'resources' as type,
            {$resourceUrlSelect} as url,
            NULL as avatar
        FROM resources r
        WHERE {$resourceWhere}
        {$resourceAccessSql}
        ORDER BY r.title ASC
        LIMIT 5
    ");
    $resourceParams = $resourcesHasDescription ? [$searchPattern, $searchPattern] : [$searchPattern];
    if ($resourcesHasGbsId) {
        $resourceParams[] = $user_id;
    }
    $stmt->execute($resourceParams);
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format resource metadata
    foreach ($resources as &$resource) {
        $resource['metadata'] = strtoupper($resource['metadata']);
    }
    $results = array_merge($results, $resources);

    // Search GBS Resources (if user has GBS access)
    if ($has_gbs_access && searchTableExists($pdo, 'gbs_resources')) {
        $stmt = $pdo->prepare("
            SELECT 
                gr.id,
                gr.title,
                CONCAT('GBS Resource - ', g.name) as description,
                gr.file_type as metadata,
                'resources' as type,
                CONCAT('gbs_group.php?id=', gr.gbs_id) as url,
                NULL as avatar
            FROM gbs_resources gr
            JOIN gbs_groups g ON gr.gbs_id = g.id
            JOIN gbs_members gm ON gr.gbs_id = gm.gbs_id
            WHERE gm.user_id = ?
            AND (gr.title LIKE ?)
            ORDER BY gr.title ASC
            LIMIT 3
        ");
        $stmt->execute([$user_id, $searchPattern]);
        $gbs_resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format GBS resource metadata
        foreach ($gbs_resources as &$resource) {
            $resource['metadata'] = 'GBS ' . strtoupper($resource['metadata']);
        }
        $results = array_merge($results, $gbs_resources);
    }

    // Search site announcements.
    if (in_array($user_role, ['admin', 'super_admin'], true) || $has_gbs_access) {
        $announcementAccessSql = $announcementsHasGbsId
            ? 'AND (a.gbs_id IS NULL OR a.gbs_id IN (SELECT gbs_id FROM gbs_members WHERE user_id = ?))'
            : '';
        $announcementMetadataSelect = $announcementsHasGbsId
            ? "CASE WHEN a.gbs_id IS NOT NULL THEN 'GBS Announcement' ELSE 'Site Announcement' END"
            : "'Site Announcement'";
        $announcementUrlSelect = $announcementsHasGbsId
            ? "CASE WHEN a.gbs_id IS NOT NULL THEN CONCAT('gbs_group.php?id=', a.gbs_id) ELSE 'announcements.php' END"
            : "'announcements.php'";
        $stmt = $pdo->prepare("
            SELECT
                a.id,
                a.title,
                LEFT(a.content, 100) as description,
                {$announcementMetadataSelect} as metadata,
                'announcements' as type,
                {$announcementUrlSelect} as url,
                NULL as avatar
            FROM announcements a
            WHERE (a.title LIKE ? OR a.content LIKE ?)
            {$announcementAccessSql}
            ORDER BY a.{$announcementsDateColumn} DESC
            LIMIT 3
        ");
        $announcementParams = [$searchPattern, $searchPattern];
        if ($announcementsHasGbsId) {
            $announcementParams[] = $user_id;
        }
        $stmt->execute($announcementParams);
        $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Truncate description if needed
        foreach ($announcements as &$announcement) {
            if (strlen($announcement['description']) >= 100) {
                $announcement['description'] .= '...';
            }
        }
        $results = array_merge($results, $announcements);
    }

    if ($has_gbs_access && searchTableExists($pdo, 'gbs_announcements')) {
        $stmt = $pdo->prepare("
            SELECT
                a.id,
                a.title,
                LEFT(a.content, 100) as description,
                CONCAT('GBS Announcement - ', g.name) as metadata,
                'announcements' as type,
                CONCAT('gbs_group.php?id=', a.gbs_id) as url,
                NULL as avatar
            FROM gbs_announcements a
            JOIN gbs_groups g ON a.gbs_id = g.id
            JOIN gbs_members gm ON a.gbs_id = gm.gbs_id
            WHERE gm.user_id = ?
              AND (a.title LIKE ? OR a.content LIKE ?)
            ORDER BY a.created_at DESC
            LIMIT 3
        ");
        $stmt->execute([$user_id, $searchPattern, $searchPattern]);
        $gbsAnnouncements = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($gbsAnnouncements as &$announcement) {
            if (strlen($announcement['description']) >= 100) {
                $announcement['description'] .= '...';
            }
        }
        unset($announcement);
        $results = array_merge($results, $gbsAnnouncements);
    }

    // Sort results by relevance (simple scoring system)
    $results = array_map(function($result) use ($query) {
        $score = 0;
        
        // Exact title match gets highest score
        if (strtolower($result['title']) === strtolower($query)) {
            $score += 100;
        }
        // Title starts with query
        elseif (stripos($result['title'], $query) === 0) {
            $score += 50;
        }
        // Title contains query
        elseif (stripos($result['title'], $query) !== false) {
            $score += 25;
        }
        
        // Description matches
        if (stripos($result['description'] ?? '', $query) !== false) {
            $score += 10;
        }
        
        $result['score'] = $score;
        return $result;
    }, $results);
    
    // Sort by score (descending) and limit results
    usort($results, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    $results = array_slice($results, 0, 10);
    
    // Remove score from output (internal use only)
    $results = array_map(function($result) {
        unset($result['score']);
        return $result;
    }, $results);

    echo json_encode($results);

} catch (PDOException $e) {
    error_log("Search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Search temporarily unavailable']);
}
?>
