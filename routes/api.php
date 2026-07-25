<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\LeaderboardEventController;
use App\Http\Controllers\Api\PracticeLeaderboardController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\TutorGradingController;
use App\Http\Controllers\Api\UserPrivacyController;
use App\Http\Controllers\Api\MidtransCallbackController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\PerformanceController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\ExamBatchController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\TopicPracticeController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\PromoController;
use App\Http\Controllers\Api\TutorController;
use App\Http\Controllers\Api\TestimonialController;

/*
|--------------------------------------------------------------------------
| API Routes — Kelasxtra (Tahap 5, revisi sesuai mvp-desain-lms-kelasxtra.md)
|--------------------------------------------------------------------------
*/

// ==================== ROUTE PUBLIK ====================

Route::post('/auth/register', [AuthController::class, 'register'])
    ->middleware('throttle:6,1'); // section 8: rate limit di login/register

Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:6,1'); // section 8: rate limit di login

Route::post('/auth/google', [AuthController::class, 'google'])
    ->middleware('throttle:10,1');

Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed'])
    ->name('verification.verify');

Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
    ->middleware('throttle:3,1');

Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:3,1');

Route::post('/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('throttle:6,1');

// Webhook Midtrans — Publik & Idempotent (Menggunakan MidtransCallbackController baru)
Route::post('/midtrans/callback', [MidtransCallbackController::class, 'handleCallback']);

Route::get('/programs', [ProgramController::class, 'index']);
Route::get('/packages', [PackageController::class, 'index']);
Route::get('/packages/recommended', [PackageController::class, 'recommended']);
Route::get('/packages/focus-topics', [PackageController::class, 'focusTopics']);
Route::get('/subscription-plans', [SubscriptionController::class, 'plans']);
Route::get('/packages/{package}', [PackageController::class, 'show']);
Route::get('/articles', [ArticleController::class, 'index']);
Route::get('/articles/{slug}', [ArticleController::class, 'show']);
Route::get('/promos/active', [PromoController::class, 'active']);
Route::get('/tutors', [TutorController::class, 'index']);
Route::get('/testimonials', [TestimonialController::class, 'index']);
Route::post('/promos/validate', [PromoController::class, 'validateCode'])
    ->middleware(['auth:sanctum', 'throttle:20,1']);

// ==================== ROUTE PRIVATE (AUTH SANCTUM) ====================

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password', [AuthController::class, 'updatePassword']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Kelas & Jadwal
    Route::get('/classes', [ClassController::class, 'index']);
    Route::get('/classes/{class}', [ClassController::class, 'show']);

    // Materi Belajar
    Route::get('/materials/{material}', [MaterialController::class, 'show']);

    // Pembelian & Riwayat Transaksi
    Route::post('/transactions/checkout', [TransactionController::class, 'checkout'])
        ->middleware('throttle:10,1'); // section 11: cegah spam checkout ke Midtrans
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/my-subscription', [SubscriptionController::class, 'mySubscription']);
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);
    Route::post('/transactions/{transaction}/resume', [TransactionController::class, 'resume'])
        ->middleware('throttle:10,1'); // section 11: cegah spam resume ke Midtrans


    // Exam Engine — Melayani latihan soal & try out batch sekaligus
    Route::post('/exams/start', [ExamController::class, 'start'])
        ->middleware('throttle:10,1'); // rate limit di mulai exam

    Route::get('/exam-attempts/{attempt}', [ExamController::class, 'show']);

    Route::post('/exam-attempts/{attempt}/answer', [ExamController::class, 'submitAnswer'])
        ->middleware('throttle:60,1'); // rate limit di submit jawaban

    Route::post('/exam-attempts/{attempt}/tab-switch', [ExamController::class, 'recordTabSwitch']);
    Route::post('/exam-attempts/{attempt}/finish', [ExamController::class, 'finish']);

    Route::get('/my-exams/latest-attempted', [ExamController::class, 'latestAttemptedExam']);
    Route::get('/my-exams', [ExamController::class, 'myExams']);
    Route::get('/exams/{exam}/summary', [ExamController::class, 'summary']);
    Route::get('/exams/{exam}/banks', [ExamController::class, 'banks']);
    Route::get('/exams/{exam}/attempts', [ExamController::class, 'attempts']);
    Route::get('/exam-attempts/{attempt}/review', [ExamController::class, 'review']);
    Route::get('/me/performance-summary', [PerformanceController::class, 'performanceSummary']);
    Route::get('/me/topic-performance', [ExamController::class, 'topicPerformance']);
    Route::get('/packages/{package}/exams', [ExamController::class, 'forPackage']);

    // Leaderboard Try Out
    Route::get('/exam-batches', [ExamBatchController::class, 'index']);
    Route::get('/exam-batches/{examBatch}/leaderboard', [LeaderboardController::class, 'index']);
    Route::get('/exam-batches/{examBatch}/leaderboard/me', [LeaderboardController::class, 'myPosition']);

    // Leaderboard Latihan Soal (mingguan)
    Route::get('/exams/leaderboard/ranked', [PracticeLeaderboardController::class, 'ranked']);
    Route::get('/exams/{exam}/leaderboard', [PracticeLeaderboardController::class, 'index']);
    Route::get('/exams/{exam}/leaderboard/me', [PracticeLeaderboardController::class, 'myPosition']);

    // Notifikasi rank-change (Beranda)
    Route::get('/leaderboard-events/me', [LeaderboardEventController::class, 'me']);
    Route::get('/leaderboard-events/feed', [LeaderboardEventController::class, 'feed']);

    // Privasi akun
    Route::patch('/user/privacy', [UserPrivacyController::class, 'update']);

    // Role-check tegas: khusus tutor & admin (section 3 & 8)
    Route::middleware('role:tutor,admin')->prefix('tutor')->group(function () {
        Route::get('/essay-queue', [TutorGradingController::class, 'index']);
        Route::post('/essay-answers/{answer}/grade', [TutorGradingController::class, 'grade']);
    });

    Route::get('/my-packages', [EnrollmentController::class, 'index']);

    // Latihan Soal per Topik/Part -- katalog TERBUKA (bukan "dimiliki" lewat
    // Package/Enrollment). Part 1 tiap topik gratis untuk siapa saja yang
    // login, Part 2+ butuh Subscription aktif yang meng-cover Program terkait.
    // Lihat AccessControlService::canAccessExamPart() untuk aturan aksesnya.
    Route::get('/latihan-soal/categories', [TopicPracticeController::class, 'categories']);
    Route::get('/latihan-soal/categories/{taxonomy}/topics', [TopicPracticeController::class, 'topics']);
    Route::get('/latihan-soal/topics/{topic}/roadmap', [TopicPracticeController::class, 'roadmap']);

});

// ── Notifikasi (leaderboard reward, dsb) ─────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
});
