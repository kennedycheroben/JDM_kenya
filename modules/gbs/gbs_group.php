<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

// Check if user is logged in
if (empty($_SESSION['user_role'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['user_role'] ?? '';
$is_super_admin = ($user_role === 'super_admin');
$gbs_id = (int)($_GET['id'] ?? 0);

if ($gbs_id <= 0) {
    header('Location: gbs_dashboard.php');
    exit;
}

// Check if user is a member of this GBS group, unless they are the JDM Leader.
if ($is_super_admin) {
    $stmt = $pdo->prepare("
        SELECT 'leader' AS role, g.name, g.slogan, g.pfp_path, g.leader_id, g.chat_restricted
        FROM gbs_groups g
        WHERE g.id = ?
    ");
    $stmt->execute([$gbs_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT gm.role, g.name, g.slogan, g.pfp_path, g.leader_id, g.chat_restricted
        FROM gbs_members gm
        JOIN gbs_groups g ON gm.gbs_id = g.id
        WHERE gm.gbs_id = ? AND gm.user_id = ?
    ");
    $stmt->execute([$gbs_id, $user_id]);
}
$membership = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$membership) {
    header('Location: gbs_dashboard.php');
    exit;
}

$is_leader = ($membership['role'] === 'leader');
$can_manage_group = $is_leader || $is_super_admin;
$can_delete_group = $is_super_admin || (int)$membership['leader_id'] === $user_id;

// Get group members
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.whatsapp_phone, u.pfp_path, gm.role, gm.joined_at
    FROM gbs_members gm
    JOIN users u ON gm.user_id = u.id
    WHERE gm.gbs_id = ?
    ORDER BY gm.role DESC, u.name ASC
");
$stmt->execute([$gbs_id]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get group announcements
$stmt = $pdo->prepare("
    SELECT a.id, a.title, a.content, a.created_at, u.name as author_name
    FROM gbs_announcements a
    JOIN users u ON a.author_id = u.id
    WHERE a.gbs_id = ?
    ORDER BY a.created_at DESC
    LIMIT 5
");
$stmt->execute([$gbs_id]);
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get group resources
$stmt = $pdo->prepare("
    SELECT r.id, r.title, r.file_path, r.file_type, r.uploaded_at, u.name as uploader_name
    FROM gbs_resources r
    JOIN users u ON r.uploaded_by = u.id
    WHERE r.gbs_id = ?
    ORDER BY r.uploaded_at DESC
    LIMIT 10
");
$stmt->execute([$gbs_id]);
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle announcements
$message = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $can_manage_group) {
    require_csrf();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_announcement') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        
        if (empty($title) || empty($content)) {
            $error = 'Title and content are required';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO gbs_announcements (gbs_id, author_id, title, content, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$gbs_id, $user_id, $title, $content]);
                $message = 'Announcement added successfully!';
                
                // Refresh announcements
                $stmt = $pdo->prepare("
                    SELECT a.*, u.name as author_name
                    FROM gbs_announcements a
                    JOIN users u ON a.author_id = u.id
                    WHERE a.gbs_id = ?
                    ORDER BY a.created_at DESC
                    LIMIT 5
                ");
                $stmt->execute([$gbs_id]);
                $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } catch (PDOException $e) {
                $error = 'Failed to add announcement: ' . $e->getMessage();
            }
        }
    }
}

ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h2><i class="bi bi-people-fill"></i> <?= escape($membership['name']) ?></h2>
                <?php if ($membership['slogan']): ?>
                    <p class="text-muted"><?= escape($membership['slogan']) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <span class="badge bg-<?= $is_leader ? 'danger' : 'primary' ?> fs-6">
                    <?= ucfirst($membership['role']) ?>
                </span>
                <a href="gbs_dashboard.php" class="btn btn-outline-secondary btn-sm ms-2">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle"></i> <?= escape($message) ?>
        <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-circle"></i> <?= escape($error) ?>
        <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Main Content -->
    <div class="col-lg-8">
        <!-- Group Info Card -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Group Information</h5>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center">
                        <?php if ($membership['pfp_path']): ?>
                            <img src="<?= escape($membership['pfp_path']) ?>" class="img-fluid rounded" style="max-height: 150px;" alt="Group Profile">
                        <?php else: ?>
                            <div class="bg-light rounded d-inline-flex align-items-center justify-content-center" style="width: 150px; height: 150px;">
                                <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-9">
                        <h6><?= escape($membership['name']) ?></h6>
                        <p class="text-muted"><?= escape($membership['slogan'] ?? 'No slogan') ?></p>
                        <p><strong>Members:</strong> <?= count($members) ?></p>
                        <p><strong>Group Leader:</strong> 
                            <?php 
                            $leader = array_filter($members, fn($m) => $m['role'] === 'leader');
                            $leader_name = reset($leader)['name'] ?? 'Unknown';
                            echo escape($leader_name);
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Members -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-people"></i> Members (<?= count($members) ?>)</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($members as $member): ?>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center p-3 border rounded">
                                <?php if ($member['pfp_path']): ?>
                                    <img src="<?= escape($member['pfp_path']) ?>" class="rounded-circle me-3" style="width: 50px; height: 50px; object-fit: cover;" alt="Profile">
                                <?php else: ?>
                                    <div class="bg-light rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                        <i class="bi bi-person text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?= escape($member['name']) ?></h6>
                                    <small class="text-muted"><?= escape(maskEmail($member['email'] ?? '')) ?></small>
                                    <div>
                                        <span class="badge bg-<?= $member['role'] === 'leader' ? 'danger' : 'secondary' ?> small">
                                            <?= ucfirst($member['role']) ?>
                                        </span>
                                        <small class="text-muted ms-2">Joined <?= date('M d, Y', strtotime($member['joined_at'])) ?></small>
                                    </div>
                                </div>
                                <?php if ($can_manage_group && $member['id'] !== $user_id): ?>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="startPrivateChat(<?= $member['id'] ?>, '<?= escape($member['name']) ?>')">
                                                    <i class="bi bi-chat"></i> Direct Chat
                                                </a>
                                            </li>
                                            <?php if ($member['role'] === 'member'): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" onclick="removeMember(<?= $member['id'] ?>)">
                                                        <i class="bi bi-person-x"></i> Remove Member
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Resources -->
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-folder"></i> Resources (<?= count($resources) ?>)</h5>
                    <?php if ($can_manage_group): ?>
                        <button class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#uploadResourceModal">
                            <i class="bi bi-upload"></i> Upload Resource
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($resources)): ?>
                    <p class="text-muted text-center">No resources uploaded yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Uploaded By</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resources as $resource): ?>
                                    <tr>
                                        <td><?= escape($resource['title']) ?></td>
                                        <td><span class="badge bg-info"><?= escape($resource['file_type']) ?></span></td>
                                        <td><?= escape($resource['uploader_name']) ?></td>
                                        <td><?= date('M d, Y', strtotime($resource['uploaded_at'])) ?></td>
                                        <td>
                                            <a href="<?= BASE_PATH ?>/modules/helpers/download_helper.php?file=<?= urlencode($resource['file_path']) ?>&name=<?= urlencode($resource['title']) ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-download"></i> Download
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Announcements -->
        <div class="card mb-4">
            <div class="card-header bg-danger text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-megaphone"></i> Announcements</h5>
                    <?php if ($can_manage_group): ?>
                        <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#announcementModal">
                            <i class="bi bi-plus"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($announcements)): ?>
                    <p class="text-muted">No announcements yet.</p>
                <?php else: ?>
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="mb-3 pb-3 border-bottom">
                            <h6 class="mb-1"><?= escape($announcement['title']) ?></h6>
                            <p class="small text-muted mb-1"><?= escape($announcement['author_name']) ?> • <?= date('M d, Y', strtotime($announcement['created_at'])) ?></p>
                            <p class="small"><?= escape($announcement['content']) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions (Leaders only) -->
        <?php if ($can_manage_group): ?>
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-lightning"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                            <i class="bi bi-person-plus"></i> Add Member
                        </button>
                        <a href="gbs_manage.php?id=<?= $gbs_id ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-gear"></i> Manage Group
                        </a>
                        <form method="post" action="gbs_manage.php" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle_chat_restriction">
                            <input type="hidden" name="gbs_id" value="<?= $gbs_id ?>">
                            <input type="hidden" name="restricted" value="<?= $membership['chat_restricted'] ? 0 : 1 ?>">
                            <button type="submit" class="btn btn-outline-<?= $membership['chat_restricted'] ? 'success' : 'warning' ?> btn-sm w-100 mt-2">
                                <i class="bi bi-<?= $membership['chat_restricted'] ? 'chat-left-text' : 'lock' ?>"></i> 
                                <?= $membership['chat_restricted'] ? 'Enable Member Chat' : 'Restrict to Leader Only' ?>
                            </button>
                        </form>
                        <?php if ($can_delete_group): ?>
                            <form method="post" action="gbs_manage.php" onsubmit="return confirm('Delete this GBS group? This cannot be undone.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_gbs_group">
                                <input type="hidden" name="gbs_id" value="<?= $gbs_id ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm w-100 mt-2">
                                    <i class="bi bi-trash"></i> Delete Group
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Group Chat -->
        <div class="card mt-4 mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-chat-left-dots"></i> Group Discussion</h5>
                <?php if ($membership['chat_restricted']): ?>
                    <span class="badge bg-warning text-dark small"><i class="bi bi-lock"></i> Restricted</span>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div id="groupChatBox" style="height: 400px; overflow-y: auto; background: #f8f9fa;" class="p-3">
                    <!-- Messages will load here -->
                    <div class="text-center text-muted py-5">
                        <div class="spinner-border spinner-border-sm mb-2"></div>
                        <p class="small">Loading discussion...</p>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white">
                <?php if ($membership['chat_restricted'] && !$can_manage_group): ?>
                    <div class="alert alert-light border-0 small mb-0 text-center">
                        <i class="bi bi-info-circle"></i> Only the GBS Leader can send messages in this group.
                    </div>
                <?php else: ?>
                    <form id="groupChatForm" class="d-flex gap-2">
                        <?= csrf_field() ?>
                        <input type="text" id="groupChatInput" class="form-control" placeholder="Type a message..." autocomplete="off">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Announcement Modal -->
<?php if ($can_manage_group): ?>
<div class="modal fade" id="announcementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_announcement">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content</label>
                        <textarea name="content" class="form-control" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Member Modal -->
<div class="modal fade" id="addMemberModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="gbs_manage.php">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <input type="hidden" name="action" value="remove_member">
                    <input type="hidden" name="gbs_id" value="<?= $gbs_id ?>">
                    <div class="mb-3">
                        <label class="form-label">Select User</label>
                        <select name="user_id" class="form-select" required>
                            <option value="">Choose a user to add</option>
                            <?php
                            // Get users not in this group
                            $stmt = $pdo->prepare("
                                SELECT u.id, u.name, u.email
                                FROM users u
                                WHERE u.id NOT IN (
                                    SELECT gm.user_id FROM gbs_members gm WHERE gm.gbs_id = ?
                                )
                                ORDER BY u.name ASC
                            ");
                            $stmt->execute([$gbs_id]);
                            $available_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($available_users as $user):
                            ?>
                                <option value="<?= $user['id'] ?>"><?= escape($user['name']) ?> (<?= escape(maskEmail($user['email'] ?? '')) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload Resource Modal -->
<div class="modal fade" id="uploadResourceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Resource</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="gbs_manage.php" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_resource">
                    <input type="hidden" name="gbs_id" value="<?= $gbs_id ?>">
                    <div class="mb-3">
                        <label class="form-label">Resource Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">File</label>
                        <input type="file" name="resource_file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.mp4,.mp3" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const gbsId = <?= $gbs_id ?>;
const meId = <?= $user_id ?>;
let lastMsgId = 0;
const chatBox = document.getElementById('groupChatBox');
const chatForm = document.getElementById('groupChatForm');
const chatInput = document.getElementById('groupChatInput');

async function fetchMessages() {
    try {
        const res = await fetch(`modules/gbs/gbs_chat_api.php?action=fetch&gbs_id=${gbsId}&last_id=${lastMsgId}`);
        const data = await res.json();
        if (data.ok && data.messages.length > 0) {
            if (lastMsgId === 0) chatBox.innerHTML = ''; // Clear loading spinner
            
            data.messages.forEach(msg => {
                appendMessage(msg);
                lastMsgId = Math.max(lastMsgId, msg.id);
            });
            scrollToBottom();
        } else if (data.ok && lastMsgId === 0) {
            chatBox.innerHTML = '<div class="text-center text-muted py-5 small">No messages yet. Start the conversation!</div>';
        }
    } catch (e) {
        console.error('Fetch error:', e);
    }
}

function appendMessage(msg) {
    const isMine = parseInt(msg.sender_id) === meId;
    const div = document.createElement('div');
    div.className = `d-flex mb-3 ${isMine ? 'justify-content-end' : 'justify-content-start'}`;
    
    const inner = `
        <div class="max-width-75">
            <div class="small text-muted mb-1 px-2 ${isMine ? 'text-end' : ''}">
                ${isMine ? 'You' : escapeHtml(msg.sender_name)}
            </div>
            <div class="p-3 rounded-3 shadow-sm ${isMine ? 'bg-primary text-white rounded-tr-0' : 'bg-white rounded-tl-0'}" style="max-width: 100%; word-wrap: break-word;">
                ${linkify(escapeHtml(msg.message_text))}
                <div class="text-end mt-1" style="font-size: 0.65rem; opacity: 0.8;">
                    ${new Date(msg.created_at.replace(' ', 'T')).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                </div>
            </div>
        </div>
    `;
    div.innerHTML = inner;
    chatBox.appendChild(div);
}

function scrollToBottom() {
    chatBox.scrollTop = chatBox.scrollHeight;
}

function escapeHtml(text) {
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}

function linkify(text) {
    return text.replace(/\b((?:https?:\/\/[^\s<]+)|(?:[A-Za-z0-9_.\/-]+\.php(?:\?[^\s<]+)?))/g, (url) => {
        // If it's a relative link within the app, make sure it works from root
        let href = url;
        return `<a href="${href}" class="fw-bold ${url.includes('php') ? 'text-decoration-underline' : ''}" style="color: inherit;" target="_self">${url}</a>`;
    });
}

if (chatForm) {
    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const msg = chatInput.value.trim();
        if (!msg) return;
        
        chatInput.value = '';
        chatInput.disabled = true;
        
        try {
            const fd = new FormData();
            fd.append('action', 'send');
            fd.append('gbs_id', gbsId);
            fd.append('message', msg);
            
            const res = await fetch('modules/gbs/gbs_chat_api.php', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            if (!data.ok) alert(data.error);
            else fetchMessages();
        } catch (e) {
            console.error('Send error:', e);
        } finally {
            chatInput.disabled = false;
            chatInput.focus();
        }
    });
}

function startPrivateChat(userId, userName) {
    // Redirect to main chat with this user
    window.location.href = `chat.php?with=${userId}`;
}

function removeMember(userId) {
    if (confirm('Are you sure you want to remove this member from the group?')) {
        const form = document.createElement('form');
        form.method = 'post';
        form.action = 'gbs_manage.php';
        const inputs = { action: 'remove_member', user_id: userId, gbs_id: gbsId };
        for (const [k, v] of Object.entries(inputs)) {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = k;
            inp.value = v;
            form.appendChild(inp);
        }
        document.body.appendChild(form);
        form.submit();
    }
}

// Initial fetch and poll
fetchMessages();
setInterval(fetchMessages, 3000);
</script>

<?php
$content = ob_get_clean();
$page_title = $membership['name'] . " - GBS Group";
include(__DIR__ . '/../portal/layout.php');
?>
