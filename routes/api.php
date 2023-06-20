<?php
use App\Http\Controllers\AdsController;
use App\Http\Controllers\DomainsController;
use App\Http\Controllers\SnippetsController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\AdminsController;
use App\Http\Controllers\DeservedAmountsController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User;
use App\Http\Middleware\VerifyKey;
use App\Http\Middleware\VerifyAdmin;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

//Auth routes
Route::prefix('v1')->group(function () {
    Route::post('auth/register', [User::class, 'register']);
    Route::post('auth/login', [User::class, 'login']);
    Route::post('auth/verify_login', [User::class, 'verify_login']);
    Route::post('auth/confirm_email', [User::class, 'confirm_email']); //send email with code
    Route::post('auth/verify_confirm_email', [User::class, 'verify_confirm_email']); //verify code
    Route::post('auth/reset_password', [User::class, 'reset_password']); //send email with code
    Route::post('auth/verify_reset_password', [User::class, 'verify_reset_password']); //verify code
    Route::post('auth/change_password', [User::class, 'change_password']); //change password

});
//User routes
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('user', [User::class, 'init_data']);
    Route::get('auth/confirmed', [User::class, 'email_confirmed']); //check if email is confirmed


    // Domains
    Route::get('user/domains', [DomainsController::class, 'get_domains_data']);
    // Domains
    Route::post('user/domains', [DomainsController::class, 'add_domain']);
    Route::delete('user/domains/{id}', [DomainsController::class, 'delete_domain']);
    // Ads
    Route::get('user/ads', [AdsController::class, 'ads_data']);
    Route::post('user/ads', [AdsController::class, 'add_ad']);
    Route::put('user/ads/{id}', [AdsController::class, 'update_ad']);
    Route::delete('user/ads/{id}', [AdsController::class, 'delete_ad']);
    // Payments'
    Route::get('user/payments', [PaymentsController::class, 'get_payments_data']);
    Route::post('payments/pay', [StripeController::class, 'pay']);
    // Packages
    Route::post('payments/switch_package', [PaymentsController::class, 'switch_package']);
    // Snippets Shown to user
    // Packages
    // Settings
    Route::post('settings/setup_payment_method', [SettingsController::class, 'setup_payment_method']);
    Route::get('settings/get_paid', [DeservedAmountsController::class, 'get_deserved_info']);
    Route::post('settings/get_paid', [PaymentsController::class, 'send_deserved']);

    Route::get('settings/delayed_amounts', [PaymentsController::class, 'delayed_amounts']);
    Route::post('settings/pay_delayed_amounts', [PaymentsController::class, 'pay_delayed_amounts']);
});
// Snippets in websites
Route::middleware([VerifyKey::class])->prefix('v1')->group(function () {
    Route::get('advend_snippets/{key}/verify_key/{domain}', [SnippetsController::class, 'verify_domain']);
    Route::post('advend_snippets/{key}/click/{from_key}', [SnippetsController::class, 'click']);
    Route::post('advend_snippets/{key}/pause_account/{paused}', [User::class, 'pause_account']);
    Route::get('advend_snippets/{key}/clicked', [SnippetsController::class, 'check_if_clicked']);
    Route::post('advend_snippets/{key}/save_payment/{clicked_from_key}/{price}', [SnippetsController::class, 'save_payment']);
    Route::get('advend_snippets/{key}/ads', [SnippetsController::class, 'ads']);
});
// Admin Routes
    // Login 
Route::prefix('v1')->group(function () {
    Route::post('admin/check_login_token', [AdminsController::class, 'check_login_token']);
    Route::post('admin/login', [AdminsController::class, 'login']);
});
    // Dashboard
Route::middleware([VerifyAdmin::class])->prefix('v1')->group(function () {
    Route::get('admin/users', [AdminsController::class, 'users']);
    Route::post('admin/users/give_credits', [AdminsController::class, 'give_credits']);
    Route::get('admin/domains', [AdminsController::class, 'domains']);
    Route::get('admin/ads', [AdminsController::class, 'ads']);
    Route::get('admin/payments', [AdminsController::class, 'payments']);
    Route::get('admin/transactions', [AdminsController::class, 'transactions']);
    Route::delete('admin/users/{id}', [AdminsController::class, 'delete_user']);
    Route::post('admin/users/pause/{id}/{paused}', [AdminsController::class, 'pause_user']);
    Route::delete('admin/domains/{id}', [AdminsController::class, 'delete_domain']);
    Route::delete('admin/ads/{id}', [AdminsController::class, 'delete_ad']);

});


