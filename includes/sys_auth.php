<?php

/**
 *  Testet ob ein User eingeloggt ist und lädt die entsprechenden Privilegien
 */
function load_auth() {
  global $user, $privileges;
  
  $user = null;
  if (isset($_SESSION['uid'])) {
    $user = sql_select("SELECT * FROM `User` WHERE `UID`='" . sql_escape($_SESSION['uid']) . "' LIMIT 1");
    if (count($user) > 0) {
      // User ist eingeloggt, Datensatz zur Verfügung stellen und Timestamp updaten
      list($user) = $user;
      sql_query("UPDATE `User` SET " . "`lastLogIn` = '" . time() . "'" . " WHERE `UID` = '" . sql_escape($_SESSION['uid']) . "' LIMIT 1;");
      $privileges = privileges_for_user($user['UID']);
      return;
    }
    unset($_SESSION['uid']);
  }
  
  // guest privileges
  $privileges = privileges_for_group(- 1);
}

/**
 * generate a salt (random string) of arbitrary length suitable for the use with crypt()
 */
function generate_salt($length = 16) {
  $alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
  $salt = "";
  for ($i = 0; $i < $length; $i ++) {
    $salt .= $alphabet[rand(0, strlen($alphabet) - 1)];
  }
  return $salt;
}

/**
 * set the password of a user
 */
function set_password($uid, $password) {
  global $crypt_alg;
  if (isset($_SESSION['ldap_user']) and $password == 'ldap-auth') {
    $result = sql_query("UPDATE `User` SET `Passwort` = 'ldap-auth', `password_recovery_token`=NULL WHERE `UID` = " . intval($uid) . " LIMIT 1");
  } else {  
    $result = sql_query("UPDATE `User` SET `Passwort` = '" . sql_escape(crypt($password, $crypt_alg . '$' . generate_salt(16) . '$')) . "', `password_recovery_token`=NULL WHERE `UID` = " . intval($uid) . " LIMIT 1");
  }
  if ($result === false) {
    engelsystem_error('Unable to update password.');
  }
  return $result;
}

/**
 * verify a password given a precomputed salt.
 * if $uid is given and $salt is an old-style salt (plain md5), we convert it automatically
 */
function verify_password($password, $salt, $uid = false) {
  global $crypt_alg;
  $correct = false;
  if (substr($salt, 0, 1) == '$') { // new-style crypt()
    $correct = crypt($password, $salt) == $salt;
  } elseif (substr($salt, 0, 7) == '{crypt}') { // old-style crypt() with DES and static salt - not used anymore
    $correct = crypt($password, '77') == $salt;
  } elseif (strlen($salt) == 32) { // old-style md5 without salt - not used anymore
    $correct = md5($password) == $salt;
  }

  if ($correct && substr($salt, 0, strlen($crypt_alg)) != $crypt_alg && $uid) {
    // this password is stored in another format than we want it to be.
    // let's update it!
    // we duplicate the query from the above set_password() function to have the extra safety of checking the old hash
    sql_query("UPDATE `User` SET `Passwort` = '" . sql_escape(crypt($password, $crypt_alg . '$' . generate_salt() . '$')) . "' WHERE `UID` = " . intval($uid) . " AND `Passwort` = '" . sql_escape($salt) . "' LIMIT 1");
  }

  //try ldap auth
  if (!$correct) {
    if (verify_ldap_password()) {
      $correct=true;
    }
  }
  
  return $correct;
}

function verify_ldap_password() {
  global $ldap_host, $ldap_basedn ,$ldap_userou;
  $ldaph=ldap_connect($ldap_host);
  ldap_set_option($ldaph, LDAP_OPT_PROTOCOL_VERSION, 3);
  if(@ldap_bind($ldaph,"uid=".ldap_escape($_REQUEST['nick']).",".$ldap_userou.",".$ldap_basedn,$_REQUEST['password'])) {
    return true;
  }
  return false;
}

function privileges_for_user($user_id) {
  $privileges = [];
  $user_privs = sql_select("SELECT `Privileges`.`name` FROM `User` JOIN `UserGroups` ON (`User`.`UID` = `UserGroups`.`uid`) JOIN `GroupPrivileges` ON (`UserGroups`.`group_id` = `GroupPrivileges`.`group_id`) JOIN `Privileges` ON (`GroupPrivileges`.`privilege_id` = `Privileges`.`id`) WHERE `User`.`UID`='" . sql_escape($user_id) . "'");
  foreach ($user_privs as $user_priv) {
    $privileges[] = $user_priv['name'];
  }
  return $privileges;
}

function privileges_for_group($group_id) {
  $privileges = [];
  $groups_privs = sql_select("SELECT * FROM `GroupPrivileges` JOIN `Privileges` ON (`GroupPrivileges`.`privilege_id` = `Privileges`.`id`) WHERE `group_id`='" . sql_escape($group_id) . "'");
  foreach ($groups_privs as $guest_priv) {
    $privileges[] = $guest_priv['name'];
  }
  return $privileges;
}
?>
