<?php

use App\Http\Controllers\Admin\AdminAnalyticsController;
use App\Http\Controllers\Admin\AdminAnnouncementController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminIntelligenceController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\AdminTipTemplateController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Auth\AdminAuthenticatedSessionController;
use App\Http\Controllers\Bookmarks\BookmarkController;
use App\Http\Controllers\Budgets\BudgetController;
use App\Http\Controllers\Categories\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentScan\DocumentScanController;
use App\Http\Controllers\DuplicateReview\DuplicateReviewController;
use App\Http\Controllers\Insights\InsightController;
use App\Http\Controllers\Intelligence\IntelligenceController;
use App\Http\Controllers\MonthForecastController;
use App\Http\Controllers\MonthlyReviewController;
use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Controllers\PublicSite\ContactController;
use App\Http\Controllers\PublicSite\IntelligencePreviewController;
use App\Http\Controllers\RecurringTransactions\RecurringOccurrenceController;
use App\Http\Controllers\RecurringTransactions\RecurringTransactionController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\SavingsGoals\SavingsGoalController;
use App\Http\Controllers\SpendingAlerts\SpendingAlertController;
use App\Http\Controllers\Tips\SavingTipController;
use App\Http\Controllers\Transactions\AiCategorySuggestionController;
use App\Http\Controllers\Transactions\TransactionController;
use App\Http\Controllers\Transactions\TransactionImportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::get('/features', function () {
    return Inertia::render('public/features');
})->name('features');

Route::get('/how-it-works', function () {
    return Inertia::render('public/how-it-works');
})->name('how-it-works');

Route::get('/about', function () {
    return Inertia::render('public/about');
})->name('about');

Route::get('/contact', function () {
    return Inertia::render('public/contact');
})->name('contact');

Route::get('/intelligence/preview', IntelligencePreviewController::class)->name('intelligence.preview');

Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('contact.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('month-forecast', MonthForecastController::class)->name('month-forecast.index');
    Route::get('monthly-review', MonthlyReviewController::class)->name('monthly-review.index');
    Route::get('savings-goal', [SavingsGoalController::class, 'index'])->name('savings-goal.index');
    Route::post('savings-goal', [SavingsGoalController::class, 'store'])->name('savings-goal.store');
    Route::put('savings-goal/{savings_goal}', [SavingsGoalController::class, 'update'])->name('savings-goal.update');
    Route::post('savings-goal/{savings_goal}/contributions', [SavingsGoalController::class, 'contribute'])->name('savings-goal.contribute');
    Route::get('reports', ReportController::class)->name('reports.index');

    Route::get('insights', [InsightController::class, 'index'])->name('insights.index');
    Route::post('insights/regenerate', [InsightController::class, 'regenerate'])->name('insights.regenerate');

    Route::post('saving-tips/{saving_tip}/dismiss', [SavingTipController::class, 'dismiss'])->name('saving-tips.dismiss');

    Route::get('bookmarks', [BookmarkController::class, 'index'])->name('bookmarks.index');
    Route::post('bookmarks/toggle', [BookmarkController::class, 'toggle'])->name('bookmarks.toggle');
    Route::delete('bookmarks/{bookmark}', [BookmarkController::class, 'destroy'])->name('bookmarks.destroy');

    Route::get('intelligence', [IntelligenceController::class, 'index'])->name('intelligence.index');
    Route::post('intelligence/assistant', [IntelligenceController::class, 'ask'])->name('intelligence.assistant.ask');
    Route::post('intelligence/signals/{signal}/dismiss', [IntelligenceController::class, 'dismiss'])->name('intelligence.signals.dismiss');
    Route::post('intelligence/signals/{signal}/review', [IntelligenceController::class, 'review'])->name('intelligence.signals.review');

    Route::get('spending-alerts', [SpendingAlertController::class, 'index'])->name('spending-alerts.index');

    Route::get('duplicate-review', [DuplicateReviewController::class, 'index'])->name('duplicate-review.index');
    Route::post('duplicate-review/{signal}/keep-both', [DuplicateReviewController::class, 'keepBoth'])->name('duplicate-review.keep-both');
    Route::post('duplicate-review/{signal}/remove', [DuplicateReviewController::class, 'remove'])->name('duplicate-review.remove');

    Route::post('document-scan/process', [DocumentScanController::class, 'process'])->name('document-scan.process');
    Route::post('document-scan/confirm', [DocumentScanController::class, 'confirm'])->name('document-scan.confirm');

    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
    Route::post('transactions/suggest-category', AiCategorySuggestionController::class)->name('transactions.suggest-category');
    Route::get('transactions/import', [TransactionImportController::class, 'create'])->name('transactions.import.create');
    Route::post('transactions/import/preview', [TransactionImportController::class, 'preview'])->name('transactions.import.preview');
    Route::post('transactions/import/confirm', [TransactionImportController::class, 'confirm'])->name('transactions.import.confirm');
    Route::post('transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::get('transactions/{transaction}', [TransactionController::class, 'show'])->withTrashed()->name('transactions.show');
    Route::get('transactions/{transaction}/edit', [TransactionController::class, 'edit'])->name('transactions.edit');
    Route::put('transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::patch('transactions/{transaction}', [TransactionController::class, 'update']);
    Route::post('transactions/{transaction}/restore', [TransactionController::class, 'restore'])->withTrashed()->name('transactions.restore');
    Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])->withTrashed()->name('transactions.destroy');

    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::patch('categories/{category}', [CategoryController::class, 'update']);
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('recurring-transactions', [RecurringTransactionController::class, 'index'])->name('recurring-transactions.index');
    Route::get('recurring-transactions/create', [RecurringTransactionController::class, 'create'])->name('recurring-transactions.create');
    Route::post('recurring-transactions', [RecurringTransactionController::class, 'store'])->name('recurring-transactions.store');
    Route::get('recurring-transactions/{recurring_transaction}/edit', [RecurringTransactionController::class, 'edit'])->name('recurring-transactions.edit');
    Route::put('recurring-transactions/{recurring_transaction}', [RecurringTransactionController::class, 'update'])->name('recurring-transactions.update');
    Route::patch('recurring-transactions/{recurring_transaction}', [RecurringTransactionController::class, 'update']);
    Route::post('recurring-transactions/{recurring_transaction}/toggle', [RecurringTransactionController::class, 'toggle'])->name('recurring-transactions.toggle');
    Route::delete('recurring-transactions/{recurring_transaction}', [RecurringTransactionController::class, 'destroy'])->name('recurring-transactions.destroy');
    Route::post('recurring-occurrences/{occurrence}/approve', [RecurringOccurrenceController::class, 'approve'])->name('recurring-occurrences.approve');
    Route::post('recurring-occurrences/{occurrence}/reject', [RecurringOccurrenceController::class, 'reject'])->name('recurring-occurrences.reject');

    Route::get('budgets', [BudgetController::class, 'index'])->name('budgets.index');
    Route::get('budgets/create', [BudgetController::class, 'create'])->name('budgets.create');
    Route::post('budgets', [BudgetController::class, 'store'])->name('budgets.store');
    Route::get('budgets/{budget}/edit', [BudgetController::class, 'edit'])->name('budgets.edit');
    Route::put('budgets/{budget}', [BudgetController::class, 'update'])->name('budgets.update');
    Route::patch('budgets/{budget}', [BudgetController::class, 'update']);
    Route::delete('budgets/{budget}', [BudgetController::class, 'destroy'])->name('budgets.destroy');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
});

/*
|--------------------------------------------------------------------------
| Admin Control Center
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('admin/login', [AdminAuthenticatedSessionController::class, 'create'])
        ->name('admin.login');
    Route::post('admin/login', [AdminAuthenticatedSessionController::class, 'store'])
        ->name('admin.login.store');
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');

    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::post('users/{user}/disable', [AdminUserController::class, 'disable'])->name('users.disable');
    Route::post('users/{user}/enable', [AdminUserController::class, 'enable'])->name('users.enable');
    Route::post('users/{user}/reset-access', [AdminUserController::class, 'resetAccess'])->name('users.reset-access');

    Route::get('categories', [AdminCategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::put('categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('announcements', [AdminAnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('announcements', [AdminAnnouncementController::class, 'store'])->name('announcements.store');
    Route::put('announcements/{announcement}', [AdminAnnouncementController::class, 'update'])->name('announcements.update');
    Route::delete('announcements/{announcement}', [AdminAnnouncementController::class, 'destroy'])->name('announcements.destroy');

    Route::get('saving-tips', [AdminTipTemplateController::class, 'index'])->name('saving-tips.index');
    Route::post('saving-tips', [AdminTipTemplateController::class, 'store'])->name('saving-tips.store');
    Route::put('saving-tips/{tip_template}', [AdminTipTemplateController::class, 'update'])->name('saving-tips.update');
    Route::delete('saving-tips/{tip_template}', [AdminTipTemplateController::class, 'destroy'])->name('saving-tips.destroy');

    Route::get('analytics', AdminAnalyticsController::class)->name('analytics');
    Route::get('intelligence', AdminIntelligenceController::class)->name('intelligence');

    Route::get('notifications', [AdminNotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [AdminNotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [AdminNotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('notifications/{notification}/unread', [AdminNotificationController::class, 'markAsUnread'])->name('notifications.unread');
    Route::delete('notifications/{notification}', [AdminNotificationController::class, 'destroy'])->name('notifications.destroy');

    Route::get('settings', [AdminSettingsController::class, 'index'])->name('settings.index');
    Route::get('settings/appearance', [AdminSettingsController::class, 'appearance'])->name('settings.appearance');
    Route::get('settings/profile', [AdminSettingsController::class, 'profile'])->name('settings.profile');
    Route::get('settings/password', [AdminSettingsController::class, 'password'])->name('settings.password');
});

Route::middleware('auth')->prefix('admin')->group(function () {
    Route::post('logout', [AdminAuthenticatedSessionController::class, 'destroy'])
        ->name('admin.logout');
});

require __DIR__.'/loans.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
