<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header("Location: index.html");
    exit();
}

// Get all users
$users = [];
$result = $conn->query("SELECT id, username FROM users ORDER BY username");
while ($row = $result->fetch_assoc()) {
    $users[$row['id']] = $row['username'];
}

// Get all conversations
$conversations = [];
$result = $conn->query("
    SELECT DISTINCT 
        LEAST(sender_id, recipient_id) as user1, 
        GREATEST(sender_id, recipient_id) as user2
    FROM messages
    ORDER BY LEAST(sender_id, recipient_id), GREATEST(sender_id, recipient_id)
");

while ($row = $result->fetch_assoc()) {
    $user1 = $row['user1'];
    $user2 = $row['user2'];
    $conversations[] = [
        'user1_id' => $user1,
        'user1_name' => $users[$user1],
        'user2_id' => $user2,
        'user2_name' => $users[$user2]
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - View All Chats</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .conversation-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .conversation {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .conversation:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .conversation h3 {
            color: #3498db;
            margin-bottom: 10px;
        }
        
        .back-btn {
            padding: 8px 15px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .chat-view {
            margin-top: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            max-height: 500px;
            overflow-y: auto;
        }
        
        .message {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 5px;
            background: #f9f9f9;
        }
        
        .message .sender {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .message .time {
            font-size: 12px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Admin Chat Viewer</h1>
        <button class="back-btn" onclick="location.href='chat.php'">Back to Chat</button>
    </div>
    
    <div class="conversation-list">
        <?php foreach ($conversations as $conv): ?>
            <div class="conversation" onclick="viewConversation(<?= $conv['user1_id'] ?>, <?= $conv['user2_id'] ?>)">
                <h3><?= htmlspecialchars($conv['user1_name']) ?> ↔ <?= htmlspecialchars($conv['user2_name']) ?></h3>
                <p>Click to view conversation</p>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="chat-view" id="chat-view" style="display: none;">
        <!-- Chat messages will be loaded here -->
    </div>
    
    <script>
        function viewConversation(user1, user2) {
            fetch(`get_conversation.php?user1=${user1}&user2=${user2}`)
                .then(response => response.json())
                .then(messages => {
                    const chatView = document.getElementById('chat-view');
                    chatView.style.display = 'block';
                    chatView.innerHTML = '';
                    
                    messages.forEach(msg => {
                        const msgDiv = document.createElement('div');
                        msgDiv.className = 'message';
                        
                        const sender = document.createElement('div');
                        sender.className = 'sender';
                        sender.textContent = msg.sender_name;
                        
                        const time = document.createElement('div');
                        time.className = 'time';
                        time.textContent = msg.timestamp;
                        
                        const content = document.createElement('div');
                        content.textContent = msg.content;
                        
                        msgDiv.appendChild(sender);
                        msgDiv.appendChild(time);
                        msgDiv.appendChild(content);
                        chatView.appendChild(msgDiv);
                    });
                    
                    // Scroll to bottom
                    chatView.scrollTop = chatView.scrollHeight;
                });
        }
    </script>
</body>
</html>
