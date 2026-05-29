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

$updateActive = $conn->prepare('UPDATE users SET last_active = NOW() WHERE id = ?');
$updateActive->bind_param('i', $userId);
$updateActive->execute();
$updateActive->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat em Tempo Real</title>
    <link rel="stylesheet" href="../../assets/css/chat.css">
</head>

<body>
    <div class="page-wrapper chat-page">
        <aside class="chat-sidebar">
            <div class="sidebar-top">
                <div>
                    <p class="sidebar-title">Chat em Tempo Real</p>
                    <p class="sidebar-subtitle">Converse com outros desenvolvedores em tempo real.</p>
                </div>
                <div class="sidebar-profile">
                    <span><?php echo htmlspecialchars($userName); ?></span>
                </div>
            </div>

            <div class="sidebar-search">
                <input id="searchInput" type="search" placeholder="Pesquisar programadores" />
            </div>

            <div id="userList" class="user-list"></div>
        </aside>

        <main class="chat-main">
            <div class="chat-main-header">
                <div>
                    <p class="chat-title">Chat de Código</p>
                    <p class="chat-description">Selecione um usuário à esquerda para iniciar uma mensagem direta.</p>
                </div>
                <div style="display:flex;gap:12px;align-items:center;">
                    <div id="requestsPanel" class="requests-panel"></div>
                    <a class="logout-button" href="../auth/logout.php">Sair</a>
                </div>
            </div>

            <section class="conversation-panel">
                <div class="conversation-header">
                    <div>
                        <p class="contact-name" id="activeChatName">Nenhum contato selecionado</p>
                        <p class="contact-status" id="activeChatStatus">Escolha um contato para começar a conversar.</p>
                    </div>
                </div>

                <div id="chatBox" class="chat-box">
                    <div class="empty-state">Escolha um contato para abrir a conversa.</div>
                </div>
                <div id="sendNotice" class="send-notice" style="display:none;color:#ef4444;margin-bottom:12px;">Você só pode enviar mensagens privadas após a solicitação ser aceita.</div>
                <form id="sendForm" class="chat-form">
                    <textarea id="messageInput" name="message" placeholder="Digite uma mensagem de programação..." required></textarea>
                    <button class="chat-submit" type="submit">Enviar</button>
                </form>
            </section>
        </main>
    </div>

    <script>
        const userList = document.getElementById('userList');
        const searchInput = document.getElementById('searchInput');
        const chatBox = document.getElementById('chatBox');
        const sendForm = document.getElementById('sendForm');
        const messageInput = document.getElementById('messageInput');
        const activeChatName = document.getElementById('activeChatName');
        const activeChatStatus = document.getElementById('activeChatStatus');

        let users = [];
        let activeContact = null;
        let pollingInterval = null;

        async function fetchUsers() {
            try {
                const response = await fetch('../api/users.php');
                if (!response.ok) throw new Error('Failed to load users');
                const data = await response.json();
                users = data.users || [];
                renderUsers(users);
                if (!activeContact && users.length) {
                    setActiveContact(users[0]);
                }
            } catch (error) {
                console.error(error);
            }
        }

        function renderUsers(list) {
            if (!list.length) {
                userList.innerHTML = '<div class="empty-state">No users found.</div>';
                return;
            }
            userList.innerHTML = list.map(user => `
                <div class="user-card" data-user-id="${user.id}">
                    <div style="display:flex;gap:12px;align-items:center;width:100%;">
                        <div class="user-avatar">${user.initials}</div>
                        <div class="user-details" style="flex:1;">
                            <span class="user-card-name">${escapeHtml(user.name)}</span>
                            <span class="user-card-status">${user.online ? 'Online' : 'Offline'}</span>
                        </div>
                        ${user.relation === 'accepted' ? '<button class="request-btn chat-open" data-user-id="'+user.id+'">Conversar</button>' :
                          (user.relation === 'pending' && user.relation_role === 'sender' ? '<button class="request-btn" disabled>Solicitado</button>' :
                          (user.relation === 'pending' && user.relation_role === 'receiver' ? '<button class="request-btn" disabled>Recebida</button>' :
                          '<button class="request-btn" data-user-id="'+user.id+'">Solicitar</button>'))}
                    </div>
                </div>
            `).join('');

            document.querySelectorAll('.user-card').forEach(el => {
                el.addEventListener('click', (e) => {
                    // ignore clicks on the request button
                    if (e.target.closest('.request-btn')) return;
                    const id = Number(el.dataset.userId);
                    const user = users.find(u => u.id === id);
                    if (user) setActiveContact(user);
                });
            });

            // request button handlers
            document.querySelectorAll('.request-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    const id = Number(btn.dataset.userId);
                    if (btn.classList.contains('chat-open')) {
                        // open chat directly
                        const user = users.find(u => u.id === id);
                        if (user) setActiveContact(user);
                        return;
                    }
                    try {
                        const formData = new FormData();
                        formData.append('receiver_id', id);
                        formData.append('message', 'Solicitação de bate-papo privado');
                        const res = await fetch('../api/send_request.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await res.json();
                        if (data.status === 'ok') {
                            btn.textContent = 'Solicitado';
                            btn.disabled = true;
                            // refresh users to update relation
                            fetchUsers();
                        }
                    } catch (err) {
                        console.error(err);
                    }
                });
            });

            // update send availability if activeContact changed
            if (activeContact) {
                const updated = users.find(u => u.id === activeContact.id);
                if (updated) {
                    activeContact = updated;
                    updateSendAvailability();
                }
            }
        }

        function setActiveContact(user) {
            activeContact = user;
            activeChatName.textContent = user.name;
            activeChatStatus.textContent = user.online ? 'Online agora' : 'Offline';
            chatBox.innerHTML = '<div class="empty-state">Carregando conversa...</div>';
            fetchMessages();
            if (pollingInterval) clearInterval(pollingInterval);
            pollingInterval = setInterval(fetchMessages, 1000);
            document.querySelectorAll('.user-card').forEach(el => el.classList.toggle('active', Number(el.dataset.userId) === user.id));
        }

        // Requests polling
        async function fetchRequests() {
            try {
                const res = await fetch('../api/fetch_requests.php');
                if (!res.ok) return;
                const data = await res.json();
                const panel = document.getElementById('requestsPanel');
                if (!panel) return;
                const count = data.requests ? data.requests.length : 0;
                if (!data.requests || data.requests.length === 0) {
                    panel.innerHTML = `<div class="requests-badge"><span class="badge-icon">🔔</span></div>`;
                    return;
                }
                panel.innerHTML = `
                    <div class="requests-badge"><span class="badge-icon">🔔</span><span class="badge-count">${count}</span></div>
                    ` + data.requests.map(r => `
                    <div class="request-item" data-request-id="${r.id}">
                        <span class="req-from">${escapeHtml(r.sender_name)}</span>
                        <button class="req-accept" data-request-id="${r.id}">Aceitar</button>
                        <button class="req-reject" data-request-id="${r.id}">Recusar</button>
                    </div>
                `).join('');

                // attach handlers
                panel.querySelectorAll('.req-accept').forEach(b => b.addEventListener('click', e => respondRequest(e, 'accept')));
                panel.querySelectorAll('.req-reject').forEach(b => b.addEventListener('click', e => respondRequest(e, 'reject')));
            } catch (err) {
                console.error(err);
            }
        }

        async function respondRequest(e, action) {
            e.stopPropagation();
            const id = Number(e.target.dataset.requestId);
            try {
                const formData = new FormData();
                formData.append('request_id', id);
                formData.append('action', action);
                const res = await fetch('../api/respond_request.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.status === 'ok') {
                    fetchRequests();
                    if (action === 'accept') {
                        // open chat with sender (server returns sender_id)
                        const senderId = data.sender_id || null;
                        if (senderId) {
                            let contact = users.find(u => Number(u.id) === Number(senderId));
                            if (contact) {
                                setActiveContact(contact);
                            } else {
                                // reload users and try again
                                await fetchUsers();
                                contact = users.find(u => Number(u.id) === Number(senderId));
                                if (contact) setActiveContact(contact);
                            }
                        } else {
                            // fallback: refresh user list
                            fetchUsers();
                        }
                    }
                }
            } catch (err) {
                console.error(err);
            }
        }

        async function fetchMessages() {
            if (!activeContact) return;
            try {
                const response = await fetch(`../api/fetch_messages.php?contact_id=${encodeURIComponent(activeContact.id)}`);
                if (!response.ok) throw new Error('Failed to load messages');
                const data = await response.json();
                chatBox.innerHTML = '';
                if (!data.messages.length) {
                    chatBox.innerHTML = '<div class="empty-state">Ainda não há mensagens. Comece a conversa.</div>';
                    return;
                }

                data.messages.forEach(msg => {
                    const messageEl = document.createElement('div');
                    messageEl.className = 'chat-message ' + (msg.is_own ? 'sent' : 'received');
                    messageEl.innerHTML = `
                        <div class="message-text">${escapeHtml(msg.message)}</div>
                        <div class="message-meta">${escapeHtml(msg.author_name)} · ${escapeHtml(msg.created_at)}</div>
                    `;
                    chatBox.appendChild(messageEl);
                });
                chatBox.scrollTop = chatBox.scrollHeight;
            } catch (error) {
                console.error(error);
            }
        }

        // Show/hide send notice depending on relation
        function updateSendAvailability() {
            const notice = document.getElementById('sendNotice');
            if (!activeContact) {
                notice.style.display = 'none';
                return;
            }
            if (activeContact.relation !== 'accepted') {
                notice.style.display = 'block';
            } else {
                notice.style.display = 'none';
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
            if (!message || !activeContact) return;
            if (activeContact.relation !== 'accepted') {
                alert('Você só pode enviar mensagens privadas após a solicitação ser aceita.');
                return;
            }

            try {
                const formData = new FormData();
                formData.append('message', message);
                formData.append('receiver_id', activeContact.id);
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

        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLowerCase();
            const filtered = users.filter(user => user.name.toLowerCase().includes(query));
            renderUsers(filtered);
        });

        fetchUsers();
        fetchRequests();
        // poll requests every 1 second
        setInterval(fetchRequests, 1000);
    </script>
</body>

</html>