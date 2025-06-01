<?php
// dashboard.php
include_once 'config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$applications = [];
$error_message = '';

try {
    $stmt = $pdo->query("SELECT application_id, application_number, applicant_name, department, status, created_at FROM applications ORDER BY created_at DESC");
    $applications = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Ralat pangkalan data: " . $e->getMessage();
}

include_once 'includes/header.php';
?>

<div class="max-w-6xl mx-auto bg-white p-10 rounded-xl shadow-2xl border border-gray-200 transform transition-all duration-300 hover:scale-[1.01]">
    <h2 class="text-4xl font-bold text-gray-900 mb-8 text-center bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-purple-700">Papan Pemuka Pentadbir</h2>
    <p class="text-center text-sm text-gray-500 mb-6">Uruskan permohonan aset di sini.</p>

    <?php if ($error_message): ?>
        <div class="p-4 mb-6 rounded-lg bg-red-100 text-red-700 border border-red-400 shadow-md flex items-center space-x-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="font-medium"><?php echo $error_message; ?></p>
        </div>
    <?php endif; ?>

    <?php if (empty($applications)): ?>
        <p class="text-center text-gray-600 text-lg">Tiada permohonan ditemui.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-300 rounded-lg overflow-hidden">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">No. Permohonan</th>
                        <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nama Pemohon</th>
                        <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Bahagian</th>
                        <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                        <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tarikh Dicipta</th>
                        <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($applications as $app): ?>
                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                            <td class="py-3 px-4 text-sm text-gray-800"><?php echo htmlspecialchars($app['application_number']); ?></td>
                            <td class="py-3 px-4 text-sm text-gray-800"><?php echo htmlspecialchars($app['applicant_name']); ?></td>
                            <td class="py-3 px-4 text-sm text-gray-800"><?php echo htmlspecialchars($app['department']); ?></td>
                            <td class="py-3 px-4 text-sm text-gray-800">
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full 
                                    <?php
                                    switch ($app['status']) {
                                        case 'submitted': echo 'bg-blue-600 text-white'; break;
                                        case 'approved': echo 'bg-green-600 text-white'; break;
                                        case 'rejected': echo 'bg-red-600 text-white'; break;
                                        case 'draft': echo 'bg-gray-600 text-white'; break;
                                        default: echo 'bg-gray-600 text-white'; break;
                                    }
                                    ?>">
                                    <?php
                                        switch ($app['status']) {
                                            case 'submitted': echo 'Dihantar'; break;
                                            case 'approved': echo 'Diluluskan'; break;
                                            case 'rejected': echo 'Ditolak'; break;
                                            case 'draft': echo 'Draf'; break;
                                            default: echo ucfirst(htmlspecialchars($app['status'])); break;
                                        }
                                    ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-800"><?php echo date('d-m-Y H:i', strtotime($app['created_at'])); ?></td>
                            <td class="py-3 px-4 text-sm text-gray-800">
                                <a href="view_application.php?id=<?php echo htmlspecialchars($app['application_id']); ?>" class="text-indigo-600 hover:text-indigo-900 font-medium transition duration-200 mr-2">Lihat</a>
                                <a href="edit_application.php?id=<?php echo htmlspecialchars($app['application_id']); ?>" class="text-blue-600 hover:text-blue-900 font-medium transition duration-200">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="mt-8 text-center">
        <a href="index.php" class="inline-block bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-8 rounded-full shadow-lg transition-all duration-300 transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-gray-300">
            Kembali ke Halaman Utama
        </a>
    </div>
</div>

<?php
include_once 'includes/footer.php';
?>