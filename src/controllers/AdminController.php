<?php
require_once __DIR__ . '/../util.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../repositories/UserRepository.php';

class AdminController {
  public static function listUsers() {
    require_admin();
    $users = UserRepository::listAll();
    json_response(['users' => $users]);
  }

  public static function createUser() {
    require_admin();
    $data = read_json();
    require_fields($data, ['email','password']);
    $name = $data['name'] ?? null;
    $role = in_array(($data['role'] ?? 'user'), ['user','admin']) ? $data['role'] : 'user';
    $status = in_array(($data['status'] ?? 'active'), ['active','disabled']) ? $data['status'] : 'active';

    if (UserRepository::findByEmail($data['email'])) {
      json_response(['error' => 'Email already exists'], 409);
      return;
    }
    $user = UserRepository::create($data['email'], $data['password'], $name, $role, $status);
    json_response(['user' => [
      'id' => (int)$user['id'],
      'email' => $user['email'],
      'name' => $user['name'],
      'avatar' => $user['avatar'] ?? null,
      'role' => $user['role'],
      'status' => $user['status']
    ]], 201);
  }

  public static function resetPassword() {
    require_admin();
    $id = (int)($_GET['id'] ?? 0);
    $data = read_json();
    require_fields($data, ['new_password']);
    if (!$id) { json_response(['error' => 'Invalid user id'], 400); return; }
    UserRepository::setPassword($id, $data['new_password']);
    json_response(['ok' => true]);
  }

  public static function updateStatus() {
    require_admin();
    $id = (int)($_GET['id'] ?? 0);
    $data = read_json();
    require_fields($data, ['status']);
    $status = $data['status'];
    if (!in_array($status, ['active','disabled'])) { json_response(['error' => 'Invalid status'], 422); return; }
    if (!$id) { json_response(['error' => 'Invalid user id'], 400); return; }
    UserRepository::updateStatus($id, $status);
    json_response(['ok' => true]);
  }

  public static function stats() {
    require_admin();
    $total = UserRepository::countAll();
    $active = UserRepository::countByStatus('active');
    $disabled = UserRepository::countByStatus('disabled');
    $admins = UserRepository::countByRole('admin');
    json_response([
      'total' => $total,
      'active' => $active,
      'disabled' => $disabled,
      'admins' => $admins
    ]);
  }

  public static function deleteUser() {
    require_admin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { json_response(['error' => 'Invalid user id'], 400); return; }
    $ok = UserRepository::delete($id);
    if (!$ok) { json_response(['error' => 'User not found'], 404); return; }
    json_response(['ok' => true]);
  }
}
