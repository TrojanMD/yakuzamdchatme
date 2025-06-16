<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

// Database configuration
require_once 'config.php';

// Get current user info
$user_id = $_SESSION['user_id'];
$is_admin = $_SESSION['is_admin'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Private Chat</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            display: flex;
            height: 100vh;
            background-color: #f5f5f5;
        }
        
        .sidebar {
            width: 300px;
            background: #2c3e50;
            color: white;
            display: flex;
            flex-direction: column;
        }
        
        .header {
            padding: 15px;
            background: #1a252f;
            text-align: center;
        }
        
        .user-info {
            padding: 10px;
            border-bottom: 1px solid #34495e;
            display: flex;
            align-items: center;
        }
        
        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }
        
        .user-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }
        
        .user {
            padding: 10px;
            margin-bottom: 5px;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .user:hover {
            background: #34495e;
        }
        
        .user.active {
            background: #3498db;
        }
        
        .user.banned {
            color: #e74c3c;
        }
        
        .admin-controls {
            padding: 10px;
            border-top: 1px solid #34495e;
        }
        
        .admin-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            margin-right: 5px;
        }
        
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            padding: 15px;
            background: #3498db;
            color: white;
            text-align: center;
        }
        
        .messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #fff;
        }
        
        .message {
            margin-bottom: 15px;
            max-width: 70%;
        }
        
        .message.sent {
            margin-left: auto;
            text-align: right;
        }
        
        .message.received {
            margin-right: auto;
        }
        
        .message-content {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 18px;
            background: #e6e6e6;
        }
        
        .message.sent .message-content {
            background: #3498db;
            color: white;
        }
        
        .message-info {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        
        .message-input {
            display: flex;
            padding: 15px;
            background: #ecf0f1;
        }
        
        .message-input input {
            flex: 1;
            padding: 10px 15px;
            border: none;
            border-radius: 20px;
            outline: none;
        }
        
        .message-input button {
            margin-left: 10px;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
        }
        
        .logout-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <button class="logout-btn" onclick="location.href='logout.php'">Logout</button>
    
    <div class="sidebar">
        <div class="header">
            <h2>Private Chat</h2>
        </div>
        
        <div class="user-info">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username']); ?>" alt="Profile">
            <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <?php if ($is_admin) echo '<span style="color:#2ecc71;"> (Admin)</span>'; ?>
        </div>
        
        <div class="user-list">
            <h3>Online Users</h3>
            <?php
            // Get all users except current user
            $sql = "SELECT id, username, is_banned FROM users WHERE id != ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($user = $result->fetch_assoc()) {
                $class = $user['is_banned'] ? 'user banned' : 'user';
                echo '<div class="'.$class.'" onclick="loadChat('.$user['id'].')">';
                echo htmlspecialchars($user['username']);
                if ($is_admin) {
                    echo '<div class="admin-controls">';
                    if ($user['is_banned']) {
                        echo '<button class="admin-btn" onclick="unbanUser('.$user['id'].', event)">Unban</button>';
                    } else {
                        echo '<button class="admin-btn" onclick="banUser('.$user['id'].', event)">Ban</button>';
                    }
                    echo '</div>';
                }
                echo '</div>';
            }
            ?>
        </div>
        
        <?php if ($is_admin): ?>
        <div class="admin-controls">
            <h3>Admin Tools</h3>
            <button onclick="viewAllChats()">View All Chats</button>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="chat-area">
        <div class="chat-header">
            <h2 id="chat-title">Select a user to chat</h2>
        </div>
        
        <div class="messages" id="messages">
            <!-- Messages will be loaded here -->
        </div>
        
        <div class="message-input">
            <input type="text" id="message-input" placeholder="Type your message..." disabled>
            <button id="send-btn" disabled>Send</button>
        </div>
    </div>
    
    <script>
        let currentChatUserId = null;
        
        function loadChat(userId) {
            currentChatUserId = userId;
            document.getElementById('message-input').disabled = false;
            document.getElementById('send-btn').disabled = false;
            
            // Update chat title
            const userElement = document.querySelector(`.user[onclick="loadChat(${userId})"]`);
            document.getElementById('chat-title').textContent = `Chat with ${userElement.textContent.trim()}`;
            
            // Load messages
            fetch(`get_messages.php?recipient_id=${userId}`)
                .then(response => response.json())
                .then(messages => {
                    const messagesContainer = document.getElementById('messages');
                    messagesContainer.innerHTML = '';
                    
                    messages.forEach(message => {
                        const messageDiv = document.createElement('div');
                        messageDiv.className = `message ${message.sender_id == <?php echo $user_id; ?> ? 'sent' : 'received'}`;
                        
                        const infoDiv = document.createElement('div');
                        infoDiv.className = 'message-info';
                        infoDiv.textContent = `${message.sender_name} - ${message.timestamp}`;
                        
                        const contentDiv = document.createElement('div');
                        contentDiv.className = 'message-content';
                        contentDiv.textContent = message.content;
                        
                        messageDiv.appendChild(infoDiv);
                        messageDiv.appendChild(contentDiv);
                        messagesContainer.appendChild(messageDiv);
                    });
                    
                    // Scroll to bottom
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                });
        }
        
        document.getElementById('send-btn').addEventListener('click', sendMessage);
        document.getElementById('message-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendMessage();
        });
        
        function sendMessage() {
            const messageInput = document.getElementById('message-input');
            const message = messageInput.value.trim();
            
            if (message && currentChatUserId) {
                fetch('send_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        recipient_id: currentChatUserId,
                        content: message
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        messageInput.value = '';
                        loadChat(currentChatUserId); // Refresh messages
                    }
                });
            }
        }
        
        // Admin functions
        function banUser(userId, event) {
            event.stopPropagation();
            if (confirm('Are you sure you want to ban this user?')) {
                fetch('admin_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'ban',
                        user_id: userId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                });
            }
        }
        
        function unbanUser(userId, event) {
            event.stopPropagation();
            if (confirm('Are you sure you want to unban this user?')) {
                fetch('admin_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'unban',
                        user_id: userId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                });
            }
        }
        
        function viewAllChats() {
            window.open('admin_view_chats.php', '_blank');
        }
    </script>
</body>
</html>
