<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setAutoRoute(false);
$routes->set404Override(static function () {
    return view('errors/app_404');
});

// Instalasi (aktif hanya sebelum writable/installed.lock ada)
$routes->get('install', 'Install::index');
$routes->post('install/database', 'Install::database');
$routes->get('install/account', 'Install::account');
$routes->post('install/finish', 'Install::finish');

// Logo sekolah (publik, tampil di halaman login)
$routes->get('school-logo', 'Branding::logo');
$routes->get('school-logo/(gov|cover)', 'Branding::asset/$1');
$routes->get('favicon.ico', 'Branding::favicon');

// Autentikasi
$routes->get('login', 'AuthController::login', ['filter' => 'guest']);
$routes->post('login', 'AuthController::attempt', ['filter' => 'guest']);
$routes->post('logout', 'AuthController::logout');

/** Rute tulis CRUD (tambah, ubah, hapus). */
$crudWrite = static function (RouteCollection $r, string $path, string $controller): void {
    $r->get($path . '/new', $controller . '::new');
    $r->post($path, $controller . '::create');
    $r->get($path . '/(:num)/edit', $controller . '::edit/$1');
    $r->post($path . '/(:num)', $controller . '::update/$1');
    $r->post($path . '/(:num)/delete', $controller . '::delete/$1');
};
/** Rute CRUD lengkap. */
$crud = static function (RouteCollection $r, string $path, string $controller) use ($crudWrite): void {
    $r->get($path, $controller . '::index');
    $crudWrite($r, $path, $controller);
};

// ------------------------------------------------------------------ Semua pengguna terautentikasi
$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes) {
    $routes->get('/', 'Dashboard::index');
    $routes->post('period', 'AcademicYears::switch');
    $routes->post('preferences/theme', 'Settings::saveTheme');
    $routes->get('settings/preferences', 'Settings::preferences');
    $routes->get('settings/security', 'Settings::security');
    $routes->post('settings/password', 'Settings::changePassword');
    $routes->get('settings/profile', 'Settings::profile');
    $routes->post('settings/profile', 'Settings::saveProfile');
    $routes->post('settings/photo', 'Settings::savePhoto');
    $routes->post('settings/teaching', 'Settings::saveTeaching');
    $routes->post('settings/photo/delete', 'Settings::deletePhoto');
    $routes->get('avatar/(:num)', 'Settings::avatar/$1');
    // Sampul Buku Agenda: admin = blanko default sekolah, guru = blanko milik sendiri
    $routes->get('cover', 'Covers::index');
    $routes->post('cover', 'Covers::save');
    $routes->get('cover-blank/(:segment)', 'Covers::image/$1');
    // Dokumen CP/TP/ATP/KKTP (guru: mapel yang diajar; admin: semua, dibagikan ke guru)
    $routes->get('curriculum/docs', 'Curriculum::docs');
    $routes->post('curriculum/docs', 'Curriculum::upload');
    $routes->get('curriculum/docs/(:num)', 'Curriculum::download/$1');
    $routes->post('curriculum/docs/(:num)/delete', 'Curriculum::delete/$1');

    // Baca saja untuk guru; pengelolaan oleh admin teknis
    $routes->get('students', 'Students::index');
    $routes->get('students/export', 'Students::export');
    $routes->get('students/(:num)/history', 'Students::history/$1');
    $routes->get('calendar', 'Calendar::index');
});

$routes->get('activity', 'Dashboard::activity', ['filter' => 'auth']);
$routes->post('notifications/read', 'Notifications::read', ['filter' => 'auth']);
$routes->post('notifications/open', 'Notifications::open', ['filter' => 'auth']);

// ------------------------------------------------------------------ Admin teknis: data induk & sistem
$routes->group('', ['filter' => 'auth:admin'], static function (RouteCollection $routes) use ($crud, $crudWrite) {
    $routes->get('settings/school', 'Settings::school');
    $routes->post('settings/school', 'Settings::saveSchool');

    $crud($routes, 'users', 'Users');
    $routes->post('users/(:num)/reset-password', 'Users::resetPassword/$1');
    $routes->post('users/(:num)/status', 'Users::setStatus/$1');
    $routes->get('teacher-activities', 'ActivityRecap::index');
    $routes->get('teacher-schedules', 'TeacherSchedules::index');
    $routes->get('promotions', 'Promotions::index');
    $routes->get('schedule-builder', static fn () => redirect()->to(site_url('schedule-builder/generate')));
    $routes->get('schedule-builder/setup', 'ScheduleBuilder::setup');
    $routes->post('schedule-builder/loads', 'ScheduleBuilder::saveLoads');
    $routes->post('schedule-builder/slots', 'ScheduleBuilder::saveSlots');
    $routes->get('schedule-builder/generate', 'ScheduleBuilder::generate');
    $routes->post('schedule-builder/generate', 'ScheduleBuilder::runGenerate');
    $routes->get('schedule-builder/upload', 'ScheduleBuilder::upload');
    $routes->post('schedule-builder/upload', 'ScheduleBuilder::runUpload');
    $routes->get('schedule-builder/preview/(:segment)', 'ScheduleBuilder::preview/$1');
    $routes->post('schedule-builder/apply/(:segment)', 'ScheduleBuilder::apply/$1');
    $routes->post('schedule-builder/discard/(:segment)', 'ScheduleBuilder::discard/$1');
    $routes->post('promotions', 'Promotions::process');
    $routes->get('teacher-schedules/piket', 'TeacherSchedules::piket');
    $routes->post('teacher-schedules/piket', 'TeacherSchedules::savePiket');
    $routes->get('imports', 'Imports::index');
    $routes->get('imports/(:segment)/template', 'Imports::template/$1');
    $routes->post('imports/confirm', 'Imports::confirm');
    $routes->post('imports/cancel', 'Imports::cancel');
    $routes->post('imports/(:segment)', 'Imports::run/$1');

    $crud($routes, 'academic-years', 'AcademicYears');
    $crud($routes, 'semesters', 'Semesters');
    $routes->post('subjects/quick', 'Subjects::quick');
    $crud($routes, 'subjects', 'Subjects');
    $routes->get('classes/(:num)/students', 'ClassGroups::students/$1');
    $crud($routes, 'classes', 'ClassGroups');
    $routes->get('students/import', 'Students::importForm');
    $routes->get('students/template', 'Students::template');
    $routes->post('students/bulk', 'Students::bulk');
    $crudWrite($routes, 'students', 'Students');
    $crudWrite($routes, 'calendar', 'Calendar');

    $routes->get('settings/backup', 'Settings::backup');
    $routes->post('settings/backup', 'Settings::createBackup');
    $routes->get('settings/backup/download/(:segment)', 'Settings::downloadBackup/$1');
    $routes->post('settings/backup/delete/(:segment)', 'Settings::deleteBackup/$1');
    $routes->post('settings/restore', 'Settings::restore');
    $routes->get('settings/audit', 'Settings::audit');
    $routes->post('settings/audit/delete', 'Settings::deleteAudit');
    // Sinkron Dapodik (Web Service atau berkas ekspor) — selalu pratinjau sebelum diterapkan
    $routes->get('dapodik', 'DapodikSync::index');
    $routes->post('dapodik/settings', 'DapodikSync::save');
    $routes->post('dapodik/pull/(gtk|siswa|rombel)', 'DapodikSync::pull/$1');
    $routes->post('dapodik/upload/(gtk|siswa|rombel)', 'DapodikSync::upload/$1');
    $routes->post('dapodik/apply', 'DapodikSync::apply');
    $routes->get('system-status', 'SystemStatus::index');
    $routes->post('system-status/cache-clear', 'SystemStatus::clearCache');
    $routes->post('system-status/logs-prune', 'SystemStatus::pruneLogs');
    $routes->post('system-status/errors-ack', 'SystemStatus::ackErrors');
    $routes->post('system-status/integrity-accept', 'SystemStatus::acceptIntegrity');
    $routes->post('system-status/update-check', 'SystemStatus::updateCheck');
    $routes->post('system-status/update-install', 'SystemStatus::installUpdate');
    $routes->post('system-status/migrate', 'SystemStatus::runMigrations');
});

// ------------------------------------------------------------------ Guru: pembelajaran & penilaian (data milik masing-masing guru)
$routes->group('', ['filter' => 'auth:teacher'], static function (RouteCollection $routes) use ($crud) {
    // CP, TP, ATP & KKTP: impor data (pratinjau dulu) + arsip dokumen
    $routes->get('curriculum', 'Curriculum::index');
    $routes->get('atp', 'CurriculumPlans::atp');
    $routes->post('atp/save', 'CurriculumPlans::saveAtp');
    $routes->get('kktp', 'CurriculumPlans::kktp');
    $routes->get('curriculum/template', 'Imports::template/curriculum');
    $routes->post('curriculum/import', 'Imports::run/curriculum');
    $routes->post('curriculum/confirm', 'Imports::confirm');
    $routes->post('curriculum/cancel', 'Imports::cancel');
    $crud($routes, 'outcomes', 'LearningOutcomes');
    $crud($routes, 'objectives', 'LearningObjectives');
    $crud($routes, 'schedules', 'Schedules');

    $routes->get('agendas/export', 'Agendas::export');
    $routes->get('agendas/embed-done', 'Agendas::embedDone');
    $routes->get('agendas/attendance', 'Agendas::attendanceFragment');
    $routes->get('agendas/(:num)/material', 'Agendas::material/$1');
    $crud($routes, 'agendas', 'Agendas');
    $routes->get('activities/(:num)/material', 'Activities::material/$1');
    $crud($routes, 'activities', 'Activities');

    $routes->get('assessments/(:num)/scores', 'Assessments::scores/$1');
    $routes->post('assessments/(:num)/scores', 'Assessments::saveScores/$1');
    $crud($routes, 'assessments', 'Assessments');

    $crud($routes, 'grading', 'GradingConfigs');
    $routes->get('recap', 'Recap::index');

    $crud($routes, 'principal-reviews', 'PrincipalReviews');
    $routes->get('reports/journal', 'Journal::index');
    $routes->get('reports', 'Reports::index');
    $routes->get('reports/(:segment)', 'Reports::show/$1');
});
