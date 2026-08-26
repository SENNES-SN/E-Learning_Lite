<?php

use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LoginController::class, 'showLoginForm']);
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Route::get('/dashboard', [LoginController::class, 'dashboard'])->name('dashboard');
Route::get('/notifications/unread-summary', [LoginController::class, 'notificationUnreadSummary'])
    ->name('notifications.unread-summary');
Route::get('/notifications', [LoginController::class, 'notifications'])->name('notifications');
Route::get('/profile', [LoginController::class, 'profile'])->name('profile');
Route::redirect('/courses', '/dashboard')->name('courses');
Route::get('/courses/{courseId}/achievements', [LoginController::class, 'courseAchievements'])
    ->name('courses.achievements')
    ->whereNumber('courseId');
Route::get('/courses/{courseId}', [LoginController::class, 'courseDetail'])->name('courses.show')->whereNumber('courseId');
Route::post('/courses/{courseId}/self-enrol', [LoginController::class, 'selfEnrollCourse'])
    ->name('courses.self-enrol')
    ->whereNumber('courseId');
Route::get('/courses/{courseId}/modules/{moduleId}', [LoginController::class, 'courseModule'])
    ->name('courses.modules.show')
    ->whereNumber('courseId')
    ->whereNumber('moduleId');
Route::post('/courses/{courseId}/modules/{moduleId}/material/complete', [LoginController::class, 'completeMaterial'])
    ->name('courses.modules.material.complete')
    ->whereNumber('courseId')
    ->whereNumber('moduleId');
Route::post('/courses/{courseId}/modules/{moduleId}/quiz/start', [LoginController::class, 'startQuizAttempt'])
    ->name('courses.modules.quiz.start')
    ->whereNumber('courseId')
    ->whereNumber('moduleId');
Route::get('/courses/{courseId}/modules/{moduleId}/quiz/attempt/{attemptId}', [LoginController::class, 'showQuizAttempt'])
    ->name('courses.modules.quiz.attempt')
    ->whereNumber('courseId')
    ->whereNumber('moduleId')
    ->whereNumber('attemptId');
Route::post('/courses/{courseId}/modules/{moduleId}/quiz/attempt/{attemptId}', [LoginController::class, 'submitQuizAttempt'])
    ->name('courses.modules.quiz.submit')
    ->whereNumber('courseId')
    ->whereNumber('moduleId')
    ->whereNumber('attemptId');
Route::get('/moodle-file-preview', [LoginController::class, 'previewMoodleFile'])
    ->name('moodle.file.preview');
Route::get('/moodle-file-download', [LoginController::class, 'downloadMoodleFile'])
    ->name('moodle.file.download');
Route::post('/courses/{courseId}/modules/{moduleId}/assignment', [LoginController::class, 'submitAssignment'])
    ->name('courses.modules.assignment.submit')
    ->whereNumber('courseId')
    ->whereNumber('moduleId');
Route::post('/courses/{courseId}/modules/{moduleId}/assignment/final-submit', [LoginController::class, 'finalSubmitAssignment'])
    ->name('courses.modules.assignment.final-submit')
    ->whereNumber('courseId')
    ->whereNumber('moduleId');
Route::post('/courses/{courseId}/modules/{moduleId}/assignment/grade', [LoginController::class, 'gradeAssignmentSubmission'])
    ->name('courses.modules.assignment.grade')
    ->whereNumber('courseId')
    ->whereNumber('moduleId');
Route::post('/courses/{courseId}/modules/{moduleId}/assignment/delete-draft', [LoginController::class, 'deleteAssignmentDraft'])
    ->name('courses.modules.assignment.delete-draft')
    ->whereNumber('courseId')
    ->whereNumber('moduleId');
Route::redirect('/user-courses', '/dashboard')->name('user.courses');
Route::get('/grades', [LoginController::class, 'grades'])->name('grades');
