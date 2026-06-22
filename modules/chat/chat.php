<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

if (empty($_SESSION['user_role'])) {
    header('Location: login.php');
    exit;
}

$meId = (int)($_SESSION['user_id'] ?? 0);
if ($meId <= 0) {
    header('Location: logout.php');
    exit;
}

function renderChatMessageText($text) {
    $escaped = escape((string)$text);
    $linked = preg_replace_callback(
        '~\b((?:https?://[^\s<]+)|(?:[A-Za-z0-9_./-]+\.php(?:\?[^\s<]+)?))~',
        function ($matches) {
            $url = $matches[1];
            $href = $url;
            return '<a href="' . $href . '" class="fw-semibold" target="_self">' . $url . '</a>';
        },
        $escaped
    );

    return nl2br($linked, false);
}

// AJAX endpoints for polling + send
if (isset($_GET['ajax']) && $_GET['ajax'] === 'fetch') {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    $with = (int)($_GET['with'] ?? 0);
    $after = (int)($_GET['after'] ?? 0);
    if ($with <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Missing chat user.']);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT m.id, m.sender_id, m.receiver_id, m.message_text, m.created_at, m.status, u.name AS sender_name
         FROM messages m
         INNER JOIN users u ON u.id = m.sender_id
         WHERE m.id > ?
           AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
         ORDER BY m.id ASC
         LIMIT 200"
    );
    $stmt->execute([$after, $meId, $with, $with, $meId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark incoming messages from $with to me as read when fetched
    try {
        $upd = $pdo->prepare("UPDATE messages SET status = 'read' WHERE sender_id = ? AND receiver_id = ? AND status = 'sent'");
        $upd->execute([$with, $meId]);
    } catch (Throwable $e) {
        error_log("Mark read (fetch) error: " . $e->getMessage());
    }

    echo json_encode(['ok' => true, 'messages' => $rows]);
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'send') {
    // Clean any previous output
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    header('Content-Type: application/json; charset=utf-8');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid request method.']);
        exit;
    }
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid session token. Please reload the page.']);
        exit;
    }
    
    $receiverId = (int)($_POST['receiver_id'] ?? 0);
    $senderId = $meId; // enforce from session; prevents spoofing
    $text = isset($_POST['message_text']) ? trim($_POST['message_text']) : '';
    
    // Log the incoming data
    error_log("CHAT SEND: receiverId=$receiverId, senderId=$senderId, meId=$meId, text_len=" . strlen($text));
    
    if ($receiverId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid receiver ID: ' . $receiverId]);
        exit;
    }
    
    if (strlen($text) === 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Message cannot be empty.']);
        exit;
    }
    
    if ($senderId <= 0) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Invalid sender ID. Please log in again.']);
        exit;
    }
    
    // Verify receiver exists
    try {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$receiverId]);
        if (!$stmt->fetchColumn()) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Receiver not found.']);
            exit;
        }
    } catch (PDOException $e) {
        error_log("Receiver check error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Database error.']);
        exit;
    }

    // Insert message
    try {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, status) VALUES (?, ?, ?, 'sent')");
        $stmt->execute([$senderId, $receiverId, $text]);
        $newId = (int)$pdo->lastInsertId();
        error_log("Message inserted: id=$newId, from=$senderId to=$receiverId");
        http_response_code(200);
        echo json_encode(['ok' => true, 'id' => $newId]);
    } catch (PDOException $e) {
        error_log("Insert error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to save message.', 'detail' => $e->getMessage()]);
    }
    exit;
}

$with = (int)($_GET['with'] ?? 0);
if ($with <= 0) {
    // Default: pick the first other user
    $stmt = $pdo->prepare('SELECT id FROM users WHERE id <> ? ORDER BY name ASC LIMIT 1');
    $stmt->execute([$meId]);
    $with = (int)($stmt->fetchColumn() ?: 0);
}

$isSuperAdmin = ($_SESSION['user_role'] ?? '') === 'super_admin';
if ($isSuperAdmin) {
    $peopleStmt = $pdo->prepare("SELECT id, name, pfp_path FROM users WHERE id <> ? ORDER BY name ASC");
} else {
    $peopleStmt = $pdo->prepare("SELECT id, name, pfp_path FROM users WHERE id <> ? AND category != 'partner' ORDER BY name ASC");
}
$peopleStmt->execute([$meId]);
$people = $peopleStmt->fetchAll(PDO::FETCH_ASSOC);

$withUser = null;
if ($with > 0) {
    $stmt = $pdo->prepare('SELECT id, name, pfp_path FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$with]);
    $withUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$history = [];
if ($withUser) {
    $stmt = $pdo->prepare(
        "SELECT m.id, m.sender_id, m.receiver_id, m.message_text, m.created_at, m.status, u.name AS sender_name
         FROM messages m
         INNER JOIN users u ON u.id = m.sender_id
         WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
         ORDER BY m.id ASC
         LIMIT 200"
    );
    $stmt->execute([$meId, $with, $with, $meId]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark any pending messages from this user to me as read on page load
    try {
        $upd = $pdo->prepare("UPDATE messages SET status = 'read' WHERE sender_id = ? AND receiver_id = ? AND status = 'sent'");
        $upd->execute([$with, $meId]);
    } catch (Throwable $e) {
        error_log("Mark read (page load) error: " . $e->getMessage());
    }
}

ob_start();
?>

<div class="row mb-3">
    <div class="col-12">
        <h2><i class="bi bi-chat-dots"></i> Chat</h2>
        <p class="text-muted mb-0">Messages are saved permanently. Please use the polite language.</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0"><i class="bi bi-people"></i> Members</h6>
            </div>
            <div class="list-group list-group-flush" style="max-height: 70vh; overflow:auto;">
                <?php foreach ($people as $p): ?>
                    <a class="list-group-item list-group-item-action d-flex gap-2 align-items-center <?= $with === (int)$p['id'] ? 'active' : '' ?>"
                       href="chat.php?with=<?= (int)$p['id'] ?>"
                       data-member-id="<?= (int)$p['id'] ?>">
                        <?php if (!empty($p['pfp_path'])): ?>
                            <img src="<?= escape($p['pfp_path']) ?>" class="rounded-circle" style="width:36px;height:36px;object-fit:cover;" alt="pfp">
                        <?php else: ?>
                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                                <i class="bi bi-person text-secondary"></i>
                            </div>
                        <?php endif; ?>
                        <div class="flex-grow-1 d-flex align-items-center justify-content-between">
                            <div class="fw-semibold"><?= escape($p['name']) ?></div>
                            <span class="badge bg-danger unread-count d-none" data-uid="<?= (int)$p['id'] ?>">0</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between">
                <div class="fw-semibold">
                    <i class="bi bi-chat"></i>
                    <?= $withUser ? escape($withUser['name']) : 'Select a member' ?>
                </div>
                <div class="small" id="typingIndicator" style="display: none; font-style: italic; opacity: 0.8;">
                    typing...
                </div>
            </div>
            <div class="card-body" style="height: 55vh; overflow:auto;" id="chatBox">
                <?php if (!$withUser): ?>
                    <div class="alert alert-info mb-0">Select a member to start chatting.</div>
                <?php else: ?>
                    <?php foreach ($history as $m): ?>
                        <?php
                            $mine = ((int)$m['sender_id'] === $meId);
                            $tickHtml = '';
                            if ($mine) {
                                $status = $m['status'] ?? 'sent';
                                if ($status === 'read') {
                                    $tickHtml = '<span class="msg-status" data-status-mid="'.(int)$m['id'].'"><svg viewBox="0 0 16 15" width="16" height="15"><path fill="#53bdeb" d="M15.01 3.316l-.478-.372a.365.365 0 0 0-.51.063L8.666 9.88a.32.32 0 0 1-.484.032l-.358-.325a.32.32 0 0 0-.484.032l-.378.48a.418.418 0 0 0 .036.54l1.32 1.267a.32.32 0 0 0 .484-.034l6.272-8.048a.366.366 0 0 0-.064-.512zm-4.1 0l-.478-.372a.365.365 0 0 0-.51.063L4.566 9.88a.32.32 0 0 1-.484.032L1.892 7.72a.366.366 0 0 0-.516.005l-.423.433a.364.364 0 0 0 .006.514l3.255 3.185a.32.32 0 0 0 .484-.033l6.272-8.048a.365.365 0 0 0-.063-.51z"/></svg></span>';
                                } elseif ($status === 'delivered') {
                                    $tickHtml = '<span class="msg-status" data-status-mid="'.(int)$m['id'].'"><svg viewBox="0 0 16 15" width="16" height="15"><path fill="#8696a0" d="M15.01 3.316l-.478-.372a.365.365 0 0 0-.51.063L8.666 9.88a.32.32 0 0 1-.484.032l-.358-.325a.32.32 0 0 0-.484.032l-.378.48a.418.418 0 0 0 .036.54l1.32 1.267a.32.32 0 0 0 .484-.034l6.272-8.048a.366.366 0 0 0-.064-.512zm-4.1 0l-.478-.372a.365.365 0 0 0-.51.063L4.566 9.88a.32.32 0 0 1-.484.032L1.892 7.72a.366.366 0 0 0-.516.005l-.423.433a.364.364 0 0 0 .006.514l3.255 3.185a.32.32 0 0 0 .484-.033l6.272-8.048a.365.365 0 0 0-.063-.51z"/></svg></span>';
                                } else {
                                    $tickHtml = '<span class="msg-status" data-status-mid="'.(int)$m['id'].'"><svg viewBox="0 0 11 9" width="11" height="9"><path fill="#8696a0" d="M3.794 8.761a.418.418 0 0 1-.58-.044L.15 5.34a.42.42 0 0 1 .043-.586l.6-.484a.418.418 0 0 1 .58.044l2.093 2.502L8.98 1.042a.419.419 0 0 1 .585-.052l.605.474a.419.419 0 0 1 .053.585l-5.85 7.156a.42.42 0 0 1-.58.056z"/></svg></span>';
                                }
                            }
                        ?>
                        <div class="d-flex mb-2 <?= $mine ? 'justify-content-end' : 'justify-content-start' ?>" data-mid="<?= (int)$m['id'] ?>">
                            <div class="p-2 rounded-3 <?= $mine ? 'bg-success text-white' : 'bg-light' ?>" style="max-width: 85%;">
                                <div class="small <?= $mine ? 'text-white-50' : 'text-muted' ?> d-flex justify-content-between align-items-center gap-2">
                                    <span><?= $mine ? 'You' : escape($m['sender_name']) ?> · <?= date('M d, H:i', strtotime($m['created_at'])) ?></span>
                                    <?= $tickHtml ?>
                                </div>
                                <div><?= renderChatMessageText($m['message_text']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-white">
                <?php if ($withUser): ?>
                    <form id="sendForm" class="d-flex gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="receiver_id" value="<?= (int)$with ?>">
                        <input type="hidden" name="sender_id" value="<?= (int)$meId ?>">
                        <input type="hidden" name="with" value="<?= (int)$with ?>">
                        <input class="form-control" name="message_text" id="msgInput" placeholder="Type a message..." autocomplete="off" required>
                        <button class="btn btn-success" type="submit"><i class="bi bi-send"></i></button>
                    </form>
                    <div class="text-muted small mt-2" id="chatStatus"></div>
                <?php else: ?>
                    <div class="text-muted small">Select a member to enable messaging.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const withId = <?= (int)$with ?>;
        const hasWith = <?= $withUser ? 'true' : 'false' ?>;
        const meId = <?= (int)$meId ?>;
        if (!hasWith) return;

        const chatBox = document.getElementById('chatBox');
        const form = document.getElementById('sendForm');
        const input = document.getElementById('msgInput');
        const status = document.getElementById('chatStatus');
        let lastMessageId = 0;
        chatBox.querySelectorAll('[data-mid]').forEach((node) => {
            const mid = parseInt(node.getAttribute('data-mid') || '0', 10);
            if (mid > lastMessageId) {
                lastMessageId = mid;
            }
        });

        /**
         * Update member badges based on per-sender unread counts
         */
        function updateMemberBadges(perSenderBreakdown) {
            if (!perSenderBreakdown || typeof perSenderBreakdown !== 'object') {
                return;
            }

            // Get all badge elements
            const badges = document.querySelectorAll('.unread-count[data-uid]');
            badges.forEach(badge => {
                const uid = parseInt(badge.getAttribute('data-uid') || '0', 10);
                const count = parseInt(perSenderBreakdown[uid] || 0, 10);
                
                if (count > 0) {
                    badge.textContent = count > 99 ? '99+' : String(count);
                    badge.classList.remove('d-none');
                    badge.style.display = 'inline-block';
                    console.log(`Updated badge for user ${uid}: ${count} unread messages`);
                } else {
                    badge.classList.add('d-none');
                    badge.style.display = 'none';
                }
            });
        }

        /**
         * Mark messages as read when user clicks on a member
         */
        async function markAsRead(senderId) {
            try {
                const res = await fetch(`mark_as_read.php?sender_id=${senderId}`, { credentials: 'same-origin' });
                const data = await res.json();
                if (!data.ok) {
                    console.warn('mark_as_read failed:', data);
                } else {
                    console.log(`Marked messages from user ${senderId} as read`);
                    // Refresh unread counts after marking as read
                    await fetchUnreadCountsOnLoad();
                }
            } catch (e) {
                console.error('mark_as_read error:', e);
            }
        }

        /**
         * Add click handlers to member links
         */
        function attachMemberClickHandlers() {
            const memberLinks = document.querySelectorAll('a[data-member-id]');
            memberLinks.forEach(link => {
                link.addEventListener('click', async (e) => {
                    const memberId = parseInt(link.getAttribute('data-member-id') || '0', 10);
                    if (memberId > 0) {
                        // Mark messages from this sender as read
                        await markAsRead(memberId);
                        
                        // Hide the badge for this member immediately
                        const badge = link.querySelector('.unread-count[data-uid]');
                        if (badge) {
                            badge.classList.add('d-none');
                        }
                    }
                });
            });
        }

        /**
         * Listen for notification updates from layout.php
         */
        window.addEventListener('notificationsUpdated', (event) => {
            const { perSenderBreakdown } = event.detail;
            updateMemberBadges(perSenderBreakdown);
        });

        /**
         * Fetch unread counts on page load
         */
        async function fetchUnreadCountsOnLoad() {
            try {
                const res = await fetch('check_new_messages.php', { credentials: 'same-origin' });
                const data = await res.json();
                if (data.ok) {
                    const perSenderBreakdown = data.per_sender_breakdown || {};
                    updateMemberBadges(perSenderBreakdown);
                    // Store in global for other pages
                    window.unreadNotifications = {
                        totalCount: parseInt(data.unread_count || 0, 10),
                        perSenderBreakdown: perSenderBreakdown
                    };
                }
            } catch (e) {
                console.error('Failed to fetch unread counts on load:', e);
            }
        }

        // Fetch unread counts immediately on page load
        fetchUnreadCountsOnLoad();

        // Also listen to layout.php polling updates (every 5 seconds)
        // Initial update if data is already available from layout.php polling
        if (window.unreadNotifications && window.unreadNotifications.perSenderBreakdown) {
            updateMemberBadges(window.unreadNotifications.perSenderBreakdown);
        }

        // Attach click handlers on page load
        attachMemberClickHandlers();
        
        // Periodically refresh unread counts (every 3 seconds) to ensure badges stay up-to-date
        setInterval(async () => {
            await fetchUnreadCountsOnLoad();
        }, 3000);

        // --- SVG WhatsApp Tick Assets ---
        const svgs = {
            sent: '<svg viewBox="0 0 11 9" width="11" height="9"><path fill="#8696a0" d="M3.794 8.761a.418.418 0 0 1-.58-.044L.15 5.34a.42.42 0 0 1 .043-.586l.6-.484a.418.418 0 0 1 .58.044l2.093 2.502L8.98 1.042a.419.419 0 0 1 .585-.052l.605.474a.419.419 0 0 1 .053.585l-5.85 7.156a.42.42 0 0 1-.58.056z"/></svg>',
            delivered: '<svg viewBox="0 0 16 15" width="16" height="15"><path fill="#8696a0" d="M15.01 3.316l-.478-.372a.365.365 0 0 0-.51.063L8.666 9.88a.32.32 0 0 1-.484.032l-.358-.325a.32.32 0 0 0-.484.032l-.378.48a.418.418 0 0 0 .036.54l1.32 1.267a.32.32 0 0 0 .484-.034l6.272-8.048a.366.366 0 0 0-.064-.512zm-4.1 0l-.478-.372a.365.365 0 0 0-.51.063L4.566 9.88a.32.32 0 0 1-.484.032L1.892 7.72a.366.366 0 0 0-.516.005l-.423.433a.364.364 0 0 0 .006.514l3.255 3.185a.32.32 0 0 0 .484-.033l6.272-8.048a.365.365 0 0 0-.063-.51z"/></svg>',
            read: '<svg viewBox="0 0 16 15" width="16" height="15"><path fill="#53bdeb" d="M15.01 3.316l-.478-.372a.365.365 0 0 0-.51.063L8.666 9.88a.32.32 0 0 1-.484.032l-.358-.325a.32.32 0 0 0-.484.032l-.378.48a.418.418 0 0 0 .036.54l1.32 1.267a.32.32 0 0 0 .484-.034l6.272-8.048a.366.366 0 0 0-.064-.512zm-4.1 0l-.478-.372a.365.365 0 0 0-.51.063L4.566 9.88a.32.32 0 0 1-.484.032L1.892 7.72a.366.366 0 0 0-.516.005l-.423.433a.364.364 0 0 0 .006.514l3.255 3.185a.32.32 0 0 0 .484-.033l6.272-8.048a.365.365 0 0 0-.063-.51z"/></svg>'
        };

        async function fetchNewMessages() {
            try {
                const res = await fetch(`chat.php?ajax=fetch&with=${withId}&after=${lastMessageId}`, { credentials: 'same-origin' });
                const data = await res.json();
                if (!data.ok) {
                    status.textContent = data.error || 'Could not refresh messages.';
                    return;
                }

                let receivedNewMessage = false;
                (data.messages || []).forEach((m) => {
                    const mid = parseInt(m.id || '0', 10);
                    if (mid > 0 && !chatBox.querySelector(`[data-mid="${mid}"]`)) {
                        renderMessage(m);
                        receivedNewMessage = true;
                    }
                    if (mid > lastMessageId) {
                        lastMessageId = mid;
                    }
                });

                if (receivedNewMessage) {
                    scrollToBottom();
                    fetchUnreadCountsOnLoad();
                }
            } catch (e) {
                console.error('Message refresh failed:', e);
                status.textContent = 'Connection issue. Retrying...';
            }
        }

        setInterval(fetchNewMessages, 2000);

        // --- WebSocket Initialization ---
        let ws = null;
        let typingTimeout = null;
        const typingIndicator = document.getElementById('typingIndicator');
        const WsHost = window.location.hostname;
        // Connect to FastAPI WebSocket Server
        function connectWebSocket() {
            ws = new WebSocket(`ws://${WsHost}:8000/ws/${meId}`);
            
            ws.onopen = () => {
                console.log('WebSocket connected. Real-time enabled.');
                status.textContent = '';
                // Tell server we've read any pending messages from this user
                ws.send(JSON.stringify({
                    type: 'seen',
                    chat_partner_id: withId
                }));
            };
            
            ws.onmessage = (event) => {
                const data = JSON.parse(event.data);
                
                if (data.type === 'chat_message') {
                    // Incoming message from chat partner
                    if (data.sender_id === withId) {
                        if (data.msg_id && chatBox.querySelector(`[data-mid="${data.msg_id}"]`)) {
                            return;
                        }
                        renderMessage({
                            id: data.msg_id,
                            sender_id: data.sender_id,
                            message_text: data.message_text,
                            created_at: data.timestamp,
                            sender_name: 'Partner'
                        });
                        if (parseInt(data.msg_id || '0', 10) > lastMessageId) {
                            lastMessageId = parseInt(data.msg_id, 10);
                        }
                        scrollToBottom();
                        
                        // Immediately acknowledge delivery
                        ws.send(JSON.stringify({
                            type: 'acknowledged',
                            msg_id: data.msg_id
                        }));
                        
                        // Since we are active in this chat, also send seen
                        ws.send(JSON.stringify({
                            type: 'seen',
                            chat_partner_id: withId
                        }));
                    } else {
                        // Incoming message from someone else, update their badge
                        fetchUnreadCountsOnLoad();
                    }
                } else if (data.type === 'message_status') {
                    // Update single message status (sent/delivered)
                    const statusIcon = document.querySelector(`.msg-status[data-status-mid="${data.msg_id}"]`);
                    if (statusIcon) {
                        statusIcon.innerHTML = svgs[data.status];
                    }
                } else if (data.type === 'bulk_message_status') {
                    // Update all unread messages from this receiver to 'read' (double blue ticks)
                    if (data.receiver_id === withId) {
                        const allTicks = chatBox.querySelectorAll('.msg-status');
                        allTicks.forEach(tick => {
                            if (tick.innerHTML !== svgs['read']) {
                                tick.innerHTML = svgs['read'];
                            }
                        });
                    }
                } else if (data.type === 'typing') {
                    // Show typing indicator
                    if (data.sender_id === withId && data.is_typing) {
                        typingIndicator.style.display = 'block';
                        clearTimeout(typingTimeout);
                        typingTimeout = setTimeout(() => {
                            typingIndicator.style.display = 'none';
                        }, 2000);
                    }
                }
            };
            
            ws.onclose = () => {
                console.log('WebSocket disconnected. Reconnecting in 3s...');
                setTimeout(connectWebSocket, 3000);
            };
        }
        
        connectWebSocket();

        // Typing Detection Handler
        input.addEventListener('input', () => {
            if (ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({
                    type: 'typing',
                    sender_id: meId,
                    receiver_id: withId,
                    is_typing: true
                }));
            }
        });

        function scrollToBottom() {
            chatBox.scrollTop = chatBox.scrollHeight;
        }

        function renderMessage(m) {
            const mine = (parseInt(m.sender_id, 10) === meId);
            const wrap = document.createElement('div');
            wrap.className = 'd-flex mb-2 ' + (mine ? 'justify-content-end' : 'justify-content-start');
            wrap.setAttribute('data-mid', m.id);

            const bubble = document.createElement('div');
            bubble.className = 'p-2 rounded-3 ' + (mine ? 'bg-success text-white' : 'bg-light');
            bubble.style.maxWidth = '85%';

            const meta = document.createElement('div');
            meta.className = 'small ' + (mine ? 'text-white-50' : 'text-muted') + ' d-flex justify-content-between align-items-center gap-2';
            
            const d = new Date(m.created_at.replace(' ', 'T'));
            const when = isNaN(d.getTime()) ? '' : d.toLocaleString(undefined, {month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit'});
            
            const metaText = document.createElement('span');
            metaText.textContent = (mine ? 'You' : (m.sender_name || '')) + (when ? (' · ' + when) : '');
            meta.appendChild(metaText);
            
            // Add initial status tick (grey single tick for sent messages)
            if (mine && m.id) {
                const tickWrap = document.createElement('span');
                tickWrap.className = 'msg-status';
                tickWrap.setAttribute('data-status-mid', m.id);
                tickWrap.innerHTML = svgs[m.status || 'sent'];
                meta.appendChild(tickWrap);
            }

            const body = document.createElement('div');
            body.innerHTML = linkifyMessage(m.message_text || '');

            bubble.appendChild(meta);
            bubble.appendChild(body);
            wrap.appendChild(bubble);
            chatBox.appendChild(wrap);
        }

        function escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = value;
            return div.innerHTML;
        }

        function linkifyMessage(value) {
            const escaped = escapeHtml(value);
            const linked = escaped.replace(/\b((?:https?:\/\/[^\s<]+)|(?:[A-Za-z0-9_.\/-]+\.php(?:\?[^\s<]+)?))/g, function (url) {
                return `<a href="${url}" class="fw-semibold" target="_self">${url}</a>`;
            });
            return linked.replace(/\n/g, '<br>');
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const text = (input.value || '').trim();
            if (!text) return;
            
            const formData = new FormData(form);
            formData.set('message_text', text);

            input.disabled = true;
            form.querySelector('button[type="submit"]').disabled = true;
            status.textContent = '';

            try {
                const res = await fetch('chat.php?ajax=send', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (!res.ok || !data.ok) {
                    status.textContent = data.error || 'Message could not be sent.';
                    return;
                }

                const newId = parseInt(data.id || '0', 10);
                renderMessage({
                    id: newId,
                    sender_id: meId,
                    receiver_id: withId,
                    message_text: text,
                    created_at: new Date().toISOString(),
                    sender_name: 'You',
                    status: 'sent'
                });
                if (newId > lastMessageId) {
                    lastMessageId = newId;
                }
                scrollToBottom();
                fetchUnreadCountsOnLoad();
            } catch (err) {
                console.error('Message send failed:', err);
                status.textContent = 'Message could not be sent. Please try again.';
                return;
            } finally {
                input.disabled = false;
                form.querySelector('button[type="submit"]').disabled = false;
                input.focus();
            }

            input.value = '';
        });

        scrollToBottom();
    })();
</script>

<?php
$content = ob_get_clean();
$page_title = "Chat - JDM Kenya";
include(__DIR__ . '/../portal/layout.php');
?>
