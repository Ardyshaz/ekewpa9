<?php
// login.php
session_start(); // Ensure session is started for messages

// Redirect if already logged in
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header('Location: dashboard.php');
    exit();
}

include_once 'includes/header.php';

$error_message = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']); // Clear the error message after displaying
?>

<div class="flex items-center justify-center min-h-[calc(100vh-160px)] bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full bg-white p-8 rounded-xl shadow-2xl border border-gray-200 transform transition-all duration-300 hover:scale-[1.01]">
        <h2 class="text-4xl font-bold text-gray-900 mb-8 text-center bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 to-purple-700">
            Log Masuk Pentadbir
        </h2>

        <?php if ($error_message): ?>
            <div class="p-4 mb-6 rounded-lg bg-red-100 text-red-700 border border-red-400 shadow-md flex items-center space-x-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="font-medium"><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>

        <form class="space-y-6" action="auth.php" method="POST">
            <div>
                <label for="username" class="block text-gray-700 text-sm font-semibold mb-2">Nama Pengguna</label>
                <input id="username" name="username" type="text" autocomplete="username" required
                       class="appearance-none relative block w-full px-4 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                       placeholder="Masukkan nama pengguna">
            </div>
            <div>
                <label for="password" class="block text-gray-700 text-sm font-semibold mb-2">Kata Laluan</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                       class="appearance-none relative block w-full px-4 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                       placeholder="Masukkan kata laluan">
            </div>

            <div>
                <button type="submit" name="login"
                        class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-200 shadow-md">
                    Log Masuk
                </button>
            </div>
        </form>

        <div class="mt-6 text-center">
            <a href="index.php" class="font-medium text-blue-600 hover:text-blue-500 transition duration-200">
                Kembali ke Halaman Utama
            </a>
        </div>
    </div>
</div>

<?php
include_once 'includes/footer.php';
?>
