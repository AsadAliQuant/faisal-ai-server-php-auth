<?php
require_once __DIR__ . '/../util.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../repositories/LicenseRepository.php';

class LicenseController {
  public static function listLicenses(): void {
    require_admin();
    $licenses = LicenseRepository::listAll();
    json_response(['licenses' => $licenses]);
  }

  public static function createLicense(): void {
    $admin = require_admin();
    $data = read_json();
    require_fields($data, ['key', 'type', 'expires_at']);

    $type = in_array($data['type'], ['standard','trial']) ? $data['type'] : 'standard';
    $assignedTo   = $data['assigned_to'] ?? null;
    $assignedName = $data['assigned_name'] ?? null;
    $notes        = $data['notes'] ?? '';

    // Normalize expires_at: accept ISO 8601 (e.g. "2027-05-21T00:00:00.000Z") → MySQL datetime
    $rawExpiry = $data['expires_at'];
    $ts = strtotime($rawExpiry);
    if (!$ts || $ts <= 0) {
      json_response(['error' => 'Invalid expires_at date: ' . $rawExpiry], 422);
      return;
    }
    $expiresAt = date('Y-m-d H:i:s', $ts);

    try {
      $license = LicenseRepository::create($data['key'], $type, $assignedTo, $assignedName, $notes, $expiresAt);
    } catch (PDOException $e) {
      log_error('LicenseCreate', $e->getMessage(), ['key' => $data['key'], 'sqlstate' => $e->getCode()]);
      if ($e->getCode() === '23000') {
        json_response(['error' => 'License key already exists: ' . $data['key']], 409);
      } else {
        json_response(['error' => 'Failed to save license: ' . $e->getMessage()], 500);
      }
      return;
    }

    $detail = $assignedTo ? "Assigned to $assignedTo" : 'Unassigned';
    LicenseRepository::logActivity('License created', $data['key'], $admin['email'], $detail);

    json_response(['license' => $license], 201);
  }

  public static function assignLicense(): void {
    $admin = require_admin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { json_response(['error' => 'Invalid license id'], 400); return; }

    $lic = LicenseRepository::findById($id);
    if (!$lic) { json_response(['error' => 'License not found'], 404); return; }
    if ($lic['status'] === 'revoked') { json_response(['error' => 'Cannot assign a revoked license'], 422); return; }

    $data = read_json();
    $email = $data['assigned_to'] ?? null;
    $name  = $data['assigned_name'] ?? null;
    $notes = $data['notes'] ?? $lic['notes'];

    LicenseRepository::assign($id, $email, $name, $notes);
    $updated = LicenseRepository::findById($id);

    $action = $email ? 'License assigned' : 'License unassigned';
    $detail = $email ? "Assigned to $email" : 'Assignment removed';
    LicenseRepository::logActivity($action, $lic['key'], $admin['email'], $detail);

    json_response(['license' => $updated]);
  }

  public static function reactivateLicense(): void {
    $admin = require_admin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { json_response(['error' => 'Invalid license id'], 400); return; }

    $lic = LicenseRepository::findById($id);
    if (!$lic) { json_response(['error' => 'License not found'], 404); return; }
    if ($lic['status'] === 'active') { json_response(['error' => 'License is already active'], 422); return; }
    if ($lic['status'] === 'expired') { json_response(['error' => 'Cannot activate an expired license. Create a new license instead.'], 422); return; }

    $data = read_json();
    $type = in_array($data['type'] ?? '', ['standard', 'trial']) ? $data['type'] : 'standard';
    $expires_at = $data['expires_at'] ?? null;

    LicenseRepository::reactivate($id, $type, $expires_at);
    $updated = LicenseRepository::findById($id);
    $logDetail = $expires_at ? "Reactivated as $type (original expiry preserved)" : "Reactivated as $type";
    LicenseRepository::logActivity('License reactivated', $lic['key'], $admin['email'], $logDetail);

    json_response(['license' => $updated]);
  }

  public static function revokeLicense(): void {
    $admin = require_admin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { json_response(['error' => 'Invalid license id'], 400); return; }

    $lic = LicenseRepository::findById($id);
    if (!$lic) { json_response(['error' => 'License not found'], 404); return; }
    if ($lic['status'] === 'revoked') { json_response(['error' => 'Already revoked'], 422); return; }

    LicenseRepository::revoke($id);
    LicenseRepository::logActivity('License revoked', $lic['key'], $admin['email'], 'Revoked by admin');

    json_response(['ok' => true]);
  }

  public static function deleteLicense(): void {
    $admin = require_admin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { json_response(['error' => 'Invalid license id'], 400); return; }

    $lic = LicenseRepository::findById($id);
    if (!$lic) { json_response(['error' => 'License not found'], 404); return; }

    LicenseRepository::delete($id);
    $assigneeNote = $lic['assigned_to'] ? " (was assigned to {$lic['assigned_to']})" : '';
    LicenseRepository::logActivity('License deleted', $lic['key'], $admin['email'], 'Deleted by admin' . $assigneeNote);

    json_response(['ok' => true]);
  }

  public static function bulkReactivate(): void {
    $admin = require_admin();
    $data = read_json();
    $ids = array_filter(array_map('intval', $data['ids'] ?? []), fn($v) => $v > 0);
    if (!$ids) { json_response(['error' => 'No valid ids provided'], 422); return; }

    $count = LicenseRepository::bulkReactivate(array_values($ids));
    LicenseRepository::logActivity('Bulk reactivate', 'multiple', $admin['email'], "$count license(s) reactivated");

    json_response(['ok' => true, 'count' => $count]);
  }

  public static function bulkRevoke(): void {
    $admin = require_admin();
    $data = read_json();
    $ids = array_filter(array_map('intval', $data['ids'] ?? []), fn($v) => $v > 0);
    if (!$ids) { json_response(['error' => 'No valid ids provided'], 422); return; }

    $count = LicenseRepository::bulkRevoke(array_values($ids));
    LicenseRepository::logActivity('Bulk revoke', 'multiple', $admin['email'], "$count license(s) revoked");

    json_response(['ok' => true, 'count' => $count]);
  }

  public static function bulkDelete(): void {
    $admin = require_admin();
    $data = read_json();
    $ids = array_filter(array_map('intval', $data['ids'] ?? []), fn($v) => $v > 0);
    if (!$ids) { json_response(['error' => 'No valid ids provided'], 422); return; }

    $count = LicenseRepository::bulkDelete(array_values($ids));
    LicenseRepository::logActivity('Bulk delete', 'multiple', $admin['email'], "$count license(s) deleted");

    json_response(['ok' => true, 'count' => $count]);
  }

  public static function getActivity(): void {
    require_admin();
    $activity = LicenseRepository::getActivity(100);
    json_response(['activity' => $activity]);
  }
}
