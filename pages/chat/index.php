<?php
session_start();
require '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare('SELECT firstname, lastname FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->bind_result($firstname, $lastname);
$stmt->fetch();
$stmt->close();
$userName = trim("$firstname $lastname");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Realtime Programming Chat</title>
    <link rel="stylesheet" href="../../assets/css/chat.css">
</head>

<body>
    <div class="page-wrapper chat-page">
        <section class="chat-panel">
            <div class="chat-header">
                <div>
                    <p class="title">Programmers Chat</p>
                    <p class="chat-intro">Discuss code, tools, APIs and best practices with other developers in real time.</p>
                </div>
                <div class="chat-header-actions">
                    <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
                    <a href="../auth/logout.php">Logout</a>
                </div>
            </div>

            <div id="chatBox" class="chat-box" data-last-id="0">
                <p class="message">Loading messages...</p>
            </div>

            <form id="sendForm" class="chat-form">
                <label>
                    <textarea id="messageInput" name="message" placeholder="Write a programming question or share a tip..." required></textarea>
                </label>
                <button class="chat-submit" type="submit">Send message</button>
            </form>
        </section>
    </div>

    <script>
        const chatBox = document.getElementById('chatBox');
        const sendForm = document.getElementById('sendForm');
        const messageInput = document.getElementById('messageInput');
        let lastId = 0;

        async function fetchMessages() {
            try {
                const response = await fetch('../api/fetch_messages.php?last_id=' + lastId);
                if (!response.ok) throw new Error('Failed to load messages');
                const data = await response.json();
                if (data.messages && data.messages.length) {
                    data.messages.forEach(msg => {
                        const messageEl = document.createElement('div');
                        messageEl.className = 'chat-message ' + (msg.is_own ? 'sent' : 'received');
                        messageEl.innerHTML = '<div>' + escapeHtml(msg.message) + '</div>' +
                            '<div class="meta">' + escapeHtml(msg.author_name) + ' · ' + escapeHtml(msg.created_at) + '</div>';
                        chatBox.appendChild(messageEl);
                        lastId = Math.max(lastId, msg.id);
                    });
                    chatBox.scrollTop = chatBox.scrollHeight;
                }
            } catch (error) {
                console.error(error);
            }
        }

        function escapeHtml(value) {
            return value
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        sendForm.addEventListener('submit', async event => {
            event.preventDefault();
            const message = messageInput.value.trim();
            if (!message) return;

            try {
                const formData = new FormData();
                formData.append('message', message);
                const response = await fetch('../api/send_message.php', {
                    method: 'POST',
                    body: formData,
                });
                const result = await response.json();
                if (result.status === 'ok') {
                    messageInput.value = '';
                    fetchMessages();
                }
            } catch (error) {
                console.error(error);
            }
        });

        fetchMessages();
        setInterval(fetchMessages, 1000);
    </script>
</body>

</html>