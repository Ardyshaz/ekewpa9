<?php
// includes/header.php
// session_start() is now in config/database.php, which is included first in other pages.
// So, no need to call session_start() here directly if config/database.php is always included first.
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem e-KEW.PA-9</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        main {
            flex: 1;
            padding: 2rem 0;
        }
        /* Custom styles for better aesthetics */
        .shadow-2xl {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        .rounded-xl {
            border-radius: 1rem;
        }
        .transition-all {
            transition-property: all;
            transition-duration: 300ms;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        }
        .hover\:scale-\[1\.01\]:hover {
            transform: scale(1.01);
        }
        .bg-clip-text {
            -webkit-background-clip: text;
            background-clip: text;
        }
        .text-transparent {
            color: transparent;
        }
    </style>
</head>
<body>
    <header class="bg-gray-800 text-white p-4 shadow-lg">
        <div class="container mx-auto flex justify-between items-center">
            <a href="index.php" class="text-2xl font-bold text-white hover:text-gray-300 transition duration-200">e-KEW.PA-9</a>
            <nav>
                <ul class="flex space-x-4">
                    <li><a href="index.php" class="hover:text-gray-300 transition duration-200">Utama</a></li>
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin'): ?>
                        <li><a href="dashboard.php" class="hover:text-gray-300 transition duration-200">Papan Pemuka</a></li>
                        <li><a href="auth.php?action=logout" class="hover:text-gray-300 transition duration-200">Log Keluar (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a></li>
                    <?php else: ?>
                        <li><a href="process.php" class="hover:text-gray-300 transition duration-200">Mohon Aset</a></li>
                        <li><a href="login.php" class="hover:text-gray-300 transition duration-200">Log Masuk</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    <main>
