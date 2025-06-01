<?php
// confirmation.php
include_once 'includes/header.php';

$status = $_GET['status'] ?? '';
$application_number = $_GET['app_num'] ?? ''; // Get the application number from URL

$message = 'Permohonan anda telah berjaya diterima!';
$messageType = 'success';

if ($status === 'error') {
    $message = 'Terdapat masalah memproses permohonan anda. Sila cuba lagi.';
    $messageType = 'error';
}
?>

<div class="flex flex-col items-center justify-center min-h-[calc(100vh-180px)] text-center">
    <div class="bg-white p-12 rounded-xl shadow-2xl border border-gray-200 max-w-md w-full transform transition-all duration-300 hover:scale-[1.01]">
        <?php if ($messageType === 'success'): ?>
            <div class="text-green-500 text-7xl mb-6 animate-bounce-in">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h2 class="text-4xl font-extrabold text-gray-900 mb-4 bg-clip-text text-transparent bg-gradient-to-r from-green-600 to-teal-700">Permohonan Dihantar!</h2>
            <p class="text-xl text-gray-700 mb-2"><?php echo $message; ?></p>
            <?php if ($application_number): ?>
                <p class="text-2xl font-bold text-blue-700 mb-8 mt-4">Nombor Permohonan Anda: <span class="font-extrabold tracking-wide"><?php echo htmlspecialchars($application_number); ?></span></p>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-red-500 text-7xl mb-6 animate-shake">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h2 class="text-4xl font-extrabold text-gray-900 mb-4 bg-clip-text text-transparent bg-gradient-to-r from-red-600 to-rose-700">Penghantaran Gagal!</h2>
            <p class="text-xl text-red-700 mb-8"><?php echo $message; ?></p>
        <?php endif; ?>

        <div class="space-y-4 mt-8">
            <a href="index.php" class="block w-full bg-gradient-to-r from-blue-600 to-indigo-700 hover:from-blue-700 hover:to-indigo-800 text-white font-bold py-3 px-4 rounded-lg shadow-lg transition-all duration-300 transform hover:scale-105">
                Pergi ke Utama
            </a>
            <a href="dashboard.php" class="block w-full bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 px-4 rounded-lg shadow-lg transition-all duration-300 transform hover:scale-105">
                Lihat Papan Pemuka
            </a>
        </div>
    </div>
</div>

<style>
    @keyframes bounceIn {
        0%, 20%, 40%, 60%, 80%, 100% {
            -webkit-animation-timing-function: cubic-bezier(0.215, 0.610, 0.355, 1.000);
            animation-timing-function: cubic-bezier(0.215, 0.610, 0.355, 1.000);
        }
        0% { opacity: 0; transform: scale3d(0.3, 0.3, 0.3); }
        20% { transform: scale3d(1.1, 1.1, 1.1); }
        40% { transform: scale3d(0.9, 0.9, 0.9); }
        60% { opacity: 1; transform: scale3d(1.03, 1.03, 1.03); }
        80% { transform: scale3d(0.97, 0.97, 0.97); }
        100% { opacity: 1; transform: scale3d(1, 1, 1); }
    }
    @keyframes shake {
        10%, 90% { transform: translate3d(-1px, 0, 0); }
        20%, 80% { transform: translate3d(2px, 0, 0); }
        30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
        40%, 60% { transform: translate3d(4px, 0, 0); }
    }
    .animate-bounce-in {
        animation: bounceIn 1s;
    }
    .animate-shake {
        animation: shake 0.82s cubic-bezier(.36,.07,.19,.97) both;
        transform: translate3d(0, 0, 0);
        backface-visibility: hidden;
        perspective: 1000px;
    }
</style>

<?php
include_once 'includes/footer.php';
?>
