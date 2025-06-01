<?php
// index.php
include_once 'includes/header.php';
?>

<div class="flex items-center justify-center min-h-[calc(100vh-160px)] bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full bg-white p-8 rounded-xl shadow-2xl border border-gray-200 transform transition-all duration-300 hover:scale-[1.01] text-center">
        <h2 class="text-4xl font-bold text-gray-900 mb-6 bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-purple-700">
            Selamat Datang ke Sistem e-KEW.PA-9
        </h2>
        <p class="mt-2 text-lg text-gray-600 mb-8">
            Sistem ini membolehkan permohonan pergerakan/pinjaman aset alih secara digital.
        </p>

        <div class="space-y-4">
            <a href="process.php" class="block w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg shadow-lg transition-all duration-300 transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-green-300">
                Mohon Pergerakan/Pinjaman Aset (Awam)
            </a>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="login.php" class="block w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-lg shadow-lg transition-all duration-300 transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-indigo-300">
                    Log Masuk Pentadbir
                </a>
            <?php else: ?>
                <a href="dashboard.php" class="block w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg shadow-lg transition-all duration-300 transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-blue-300">
                    Pergi ke Papan Pemuka (Admin)
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include_once 'includes/footer.php';
?>
