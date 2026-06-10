<?php
session_start();
include 'db.php';

$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;

// If remember cookie exists, remove corresponding token(s) from DB
if (!empty($_COOKIE['IMP_REMEMBER_ME'])) {
	$rawToken = $_COOKIE['IMP_REMEMBER_ME'];
	$tokenHash = hash('sha256', $rawToken);
	try {
		$delStmt = $conn->prepare("DELETE FROM remember_tokens WHERE token_hash = ? LIMIT 1");
		if ($delStmt) {
			$delStmt->bind_param('s', $tokenHash);
			$delStmt->execute();
			$delStmt->close();
		}
	} catch (Exception $e) {
		// ignore DB errors
	}
}

// Additionally remove tokens for current user (optional cleanup)
if ($userId) {
	try {
		$delUserStmt = $conn->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
		if ($delUserStmt) {
			$delUserStmt->bind_param('i', $userId);
			$delUserStmt->execute();
			$delUserStmt->close();
		}
	} catch (Exception $e) {
		// ignore
	}
}

// Remove cookie on client
$secureFlag = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
setcookie('IMP_REMEMBER_ME', '', time() - 3600, '/', '', $secureFlag, true);

// Destroy session
session_unset();
session_destroy();

header("Location: login.php?success=" . urlencode("Logged out successfully."));
exit();
