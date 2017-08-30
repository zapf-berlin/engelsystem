<?php

function login_title() {
  return _("Login");
}

function register_title() {
  return _("Register");
}

function logout_title() {
  return _("Logout");
}

// Engel registrieren
function guest_register() {
    global $tshirt_sizes, $enable_tshirt_size, $default_theme, $user, $min_password_length, $ldap_host, $ldap_basedn, $ldap_userou;
  
  $event_config = EventConfig();
  
  $msg = "";
  $nick = "";
  $lastname = "";
  $prename = "";
  $age = "";
  $tel = "";
  $dect = "";
  $mobile = "";
  $mail = "";
  $email_shiftinfo = true;
  $email_by_human_allowed = false;
  $jabber = "";
  $hometown = "";
  $comment = "";
  $tshirt_size = '';
  $password_hash = "";
  $selected_angel_types = [];
  $planned_arrival_date = null;
  
  $angel_types_source = AngelTypes();
  $angel_types = [];
  foreach ($angel_types_source as $angel_type) {
    $angel_types[$angel_type['id']] = $angel_type['name'] . ($angel_type['restricted'] ? " (restricted)" : "");
    if (! $angel_type['restricted']) {
      $selected_angel_types[] = $angel_type['id'];
    }
  }
  
  if (isset($_REQUEST['submit'])) {
    $valid = true;
    
    if (isset($_REQUEST['nick']) && strlen(User_validate_Nick($_REQUEST['nick'])) > 1) {
      $nick = User_validate_Nick($_REQUEST['nick']);
      if (sql_num_query("SELECT * FROM `User` WHERE `Nick`='" . sql_escape($nick) . "' LIMIT 1") > 0) {
        $valid = false;
        $msg .= error(sprintf(_("Your nick &quot;%s&quot; already exists."), $nick), true);
      }
    } else {
      $valid = false;
      $msg .= error(sprintf(_("Your nick &quot;%s&quot; is too short (min. 2 characters)."), User_validate_Nick($_REQUEST['nick'])), true);
    }
    
    if (isset($_REQUEST['mail']) && strlen(strip_request_item('mail')) > 0) {
      $mail = strip_request_item('mail');
      if (! check_email($mail)) {
        $valid = false;
        $msg .= error(_("E-mail address is not correct."), true);
      }
    } else {
      $valid = false;
      $msg .= error(_("Please enter your e-mail."), true);
    }
    
    if ($enable_tshirt_size) {
      if (isset($_REQUEST['tshirt_size']) && isset($tshirt_sizes[$_REQUEST['tshirt_size']]) && $_REQUEST['tshirt_size'] != '') {
        $tshirt_size = $_REQUEST['tshirt_size'];
      } else {
        $valid = false;
        $msg .= error(_("Please select your shirt size."), true);
      }
    }

    if (isset($_SESSION['ldap_user'])) {
      $_REQUEST['password'] = "ldap-auth";
    } else {
      if (isset($_REQUEST['password']) && strlen($_REQUEST['password']) >= $min_password_length) {
        if ($_REQUEST['password'] != $_REQUEST['password2']) {
          $valid = false;
          $msg .= error(_("Your passwords don't match."), true);
        }
      } else {
        $valid = false;
        $msg .= error(sprintf(_("Your password is too short (please use at least %s characters)."), $min_password_length), true);
      }
    }
    
    $selected_angel_types = [];
    foreach (array_keys($angel_types) as $angel_type_id) {
      if (isset($_REQUEST['angel_types_' . $angel_type_id])) {
        $selected_angel_types[] = $angel_type_id;
      }
    }
    
    // Trivia
    if (isset($_REQUEST['lastname']) && strlen(strip_request_item('lastname')) > 0) {
      $lastname = strip_request_item('lastname');
    } else {
      $valid = false;
      $msg .= error(_("Please enter your last name."), true);
    }
    if (isset($_REQUEST['prename']) && strlen(strip_request_item('prename')) > 0) {
      $prename = strip_request_item('prename');
    } else {
      $valid = false;
      $msg .= error(_("Please enter your first name."), true);
    }
    if (isset($_REQUEST['mobile']) && strlen(strip_request_item('mobile')) > 0) {
      $mobile = strip_request_item('mobile');
    } else {
      $valid = false;
      $msg .= error(_("Please enter your mobile number."), true);
    }
    if (isset($_REQUEST['hometown']) && strlen(strip_request_item('hometown')) > 0) {
      $hometown = strip_request_item('hometown');
    } else {
      $valid = false;
      $msg .= error(_("Please enter your hometown."), true);
    }
    if (isset($_REQUEST['comment'])) {
      $comment = strip_request_item_nl('comment');
    }
    
    if ($valid) {
      sql_query("
          INSERT INTO `User` SET 
          `color`='" . sql_escape($default_theme) . "', 
          `Nick`='" . sql_escape($nick) . "', 
          `Vorname`='" . sql_escape($prename) . "', 
          `Name`='" . sql_escape($lastname) . "', 
          `Alter`='" . sql_escape($age) . "', 
          `Telefon`='" . sql_escape($tel) . "', 
          `DECT`='" . sql_escape($dect) . "', 
          `Handy`='" . sql_escape($mobile) . "', 
          `email`='" . sql_escape($mail) . "', 
          `email_shiftinfo`=" . sql_bool($email_shiftinfo) . ", 
          `email_by_human_allowed`=" . sql_bool($email_by_human_allowed) . ",
          `jabber`='" . sql_escape($jabber) . "',
          `Size`='" . sql_escape($tshirt_size) . "', 
          `Passwort`='" . sql_escape($password_hash) . "', 
          `kommentar`='" . sql_escape($comment) . "', 
          `Hometown`='" . sql_escape($hometown) . "', 
          `force_active`=" . sql_bool(true) . ", 
          `CreateDate`=NOW(), 
          `Sprache`='" . sql_escape("de_DE.UTF-8") . "',
          `arrival_date`=NULL,
          `planned_arrival_date`='" . sql_escape($planned_arrival_date) . "'");
      
      // Assign user-group and set password
      $user_id = sql_id();
      sql_query("INSERT INTO `UserGroups` SET `uid`='" . sql_escape($user_id) . "', `group_id`=-2");
      set_password($user_id, $_REQUEST['password']);
      
      // Assign angel-types
      $user_angel_types_info = [];
      foreach ($selected_angel_types as $selected_angel_type_id) {
        sql_query("INSERT INTO `UserAngelTypes` SET `user_id`='" . sql_escape($user_id) . "', `angeltype_id`='" . sql_escape($selected_angel_type_id) . "'");
        $user_angel_types_info[] = $angel_types[$selected_angel_type_id];
      }
      
      engelsystem_log("User " . User_Nick_render(User($user_id)) . " signed up as: " . join(", ", $user_angel_types_info));
      success(_("Angel registration successful!"));
      
      // User is already logged in - that means a supporter has registered an angel. Return to register page.
      if (isset($user)) {
        redirect(page_link_to('register'));
      }
      
      // If a welcome message is present, display registration success page.
      if ($event_config != null && $event_config['event_welcome_msg'] != null) {
        return User_registration_success_view($event_config['event_welcome_msg']);
      }
      
      redirect('?');
    }
  }
  $password_required=true;
  if (isset($_SESSION['ldap_user'])) {
      $nick=$_SESSION['ldap_user'];
      $ldaph=ldap_connect($ldap_host);
      ldap_set_option($ldaph, LDAP_OPT_PROTOCOL_VERSION, 3);
      $r = ldap_search($ldaph,$ldap_userou.",".$ldap_basedn,"(&(objectClass=inetOrgPerson)(uid=".ldap_escape($nick)."))",array("sn","givenName","mail"));
      $entries = ldap_get_entries($ldaph,$r);
      $prename = $entries[0]['givenname'][0];
      $lastname = $entries[0]['sn'][0];
      $mail = $entries[0]['mail'][0];
      $password_required=false;
  }
    
  $buildup_start_date = time();
  $teardown_end_date = null;
  if ($event_config != null) {
    if (isset($event_config['buildup_start_date'])) {
      $buildup_start_date = $event_config['buildup_start_date'];
    }
    if(isset($event_config['teardown_end_date'])) {
      $teardown_end_date = $event_config['teardown_end_date'];
    }
  }
  
  return page_with_title(register_title(), [
      _("By completing this form you're registering as a Chaos-Angel. This script will create you an account in the angel task scheduler."),
      $msg,
      msg(),
      form([
          div('row', [
              div('col-md-6', [
                  div('row', [
                      div('col-sm-4', [
                          form_text('nick', _("Nick") . ' ' . entry_required(), $nick) 
                      ]),
                      div('col-sm-8', [
                          form_email('mail', _("E-Mail") . ' ' . entry_required(), $mail),
                      ]) 
                  ]),
                  div('row', [
                      div('col-sm-6', [
                          form_text('prename', _("First name") . ' ' . entry_required(), $prename) 
                      ]),
                      div('col-sm-6', [
                          form_text('lastname', _("Last name") . ' ' . entry_required(), $lastname) 
                      ])
                  ]),
                  div('row', [
                      div('col-sm-6', [
                          $password_required ? form_password('password', _("Password") . ' ' . entry_required()) : ''
                      ]),
                      div('col-sm-6', [
                          $password_required ? form_password('password2', _("Confirm password") . ' ' . entry_required()) : ''
                      ]) 
                  ]),
                  form_checkboxes('angel_types', _("What do you want to do?") . sprintf(" (<a href=\"%s\">%s</a>)", page_link_to('angeltypes') . '&action=about', _("Description of job types")), $angel_types, $selected_angel_types),
                  form_info("", _("Restricted angel types need will be confirmed later by a supporter. You can change your selection in the options section.")) 
              ]),
              div('col-md-6', [
                  div('row', [
                      div('col-sm-6', [
                          form_text('mobile', _("Mobile (used only for important problems)") . ' ' . entry_required(), $mobile) 
                      ])
                  ]),
                  div('row', [
                  ]),
                  div('row', [
                      div('col-sm-6', [
                          form_text('hometown', _("Hometown") . ' ' . entry_required(), $hometown) 
                      ]),
                      div('col-sm-4', [
                          $enable_tshirt_size ? form_select('tshirt_size', _("Shirt size") . ' ' . entry_required(), $tshirt_sizes, $tshirt_size) : '' 
                      ])
                  ]),
                  form_info(entry_required() . ' = ' . _("Entry required!")) 
              ]) 
          ]),
          // form_textarea('comment', _("Did you help at former CCC events and which tasks have you performed then?"), $comment),
          form_submit('submit', _("Register")) 
      ]) 
  ]);
}

function entry_required() {
  return '<span class="text-info glyphicon glyphicon-warning-sign"></span>';
}

function guest_logout() {
  session_destroy();
  redirect(page_link_to("start"));
}

function guest_login() {
  $nick = "";
  
  unset($_SESSION['uid']);
  $valid = true;
  
  if (isset($_REQUEST['submit'])) {
    
    if (isset($_REQUEST['nick']) && strlen(User_validate_Nick($_REQUEST['nick'])) > 0) {
      $nick = User_validate_Nick($_REQUEST['nick']);
      $login_user = sql_select("SELECT * FROM `User` WHERE `Nick`='" . sql_escape($nick) . "'");
      if (count($login_user) > 0) {
        $login_user = $login_user[0];
        if (isset($_REQUEST['password'])) {
          if (! verify_password($_REQUEST['password'], $login_user['Passwort'], $login_user['UID'])) {
            $valid = false;
            error(_("Your password is incorrect.  Please try it again."));
          }
        } else {
          $valid = false;
          error(_("Please enter a password."));
        }
      } else {
        if (verify_ldap_password()) {
          $_SESSION['ldap_user']=$_REQUEST['nick'];
          redirect(page_link_to('register'));
        }  
        $valid = false;
        error(_("No user was found with that Nickname. Please try again. If you are still having problems, ask a Dispatcher."));
      }
    } else {
      $valid = false;
      error(_("Please enter a nickname."));
    }
    
    if ($valid) {
      $_SESSION['uid'] = $login_user['UID'];
      $_SESSION['locale'] = $login_user['Sprache'];
      
      redirect(page_link_to('news'));
    }
  }
  
  $event_config = EventConfig();
  
  return page([
      div('col-md-12', [
          div('row', [
              EventConfig_countdown_page($event_config) 
          ]),
          div('row', [
              div('col-sm-6 col-sm-offset-3 col-md-4 col-md-offset-4', [
                  div('panel panel-primary first', [
                      div('panel-heading', [
                          '<span class="icon-icon_angel"></span> ' . _("Login") 
                      ]),
                      div('panel-body', [
                          msg(),
                          form([
                              form_text_placeholder('nick', _("Nick"), $nick),
                              form_password_placeholder('password', _("Password")),
                              form_submit('submit', _("Login")),
                              ! $valid ? buttons([
                                  button(page_link_to('user_password_recovery'), _("I forgot my password")) 
                              ]) : '' 
                          ]) 
                      ]),
                      div('panel-footer', [
                          glyph('info-sign') . _("Please note: You have to activate cookies!") 
                      ]) 
                  ]) 
              ]) 
          ]),
          div('row', [
              div('col-sm-6 text-center', [
                  heading(register_title(), 2),
                  get_register_hint() 
              ]),
              div('col-sm-6 text-center', [
                  heading(_("What can I do?"), 2),
                  '<p>' . _("Please read about the jobs you can do to help us.") . '</p>',
                  buttons([
                      button(page_link_to('angeltypes') . '&action=about', _("Teams/Job description") . ' &raquo;') 
                  ]) 
              ]) 
          ]) 
      ]) 
  ]);
}

function get_register_hint() {
  global $privileges;
  
  if (in_array('register', $privileges)) {
    return join('', [
        '<p>' . _("Please sign up, if you want to help us!") . '</p>',
        buttons([
            button(page_link_to('register'), register_title() . ' &raquo;') 
        ]) 
    ]);
  }
  
  //FIXME: return error(_("Registration is disabled."), true);
  return error("Registration is <a href='https://engelsystem.de/33c3/overwhelmed.html'>disabled</a>.", true);
}
?>
