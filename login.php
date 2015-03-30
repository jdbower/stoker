<?php
// Only show the login button if you haven't just submitted a login
// and you don't have an active session.
if ( ! isset($_POST[id_token]) && ( ! isset($_SESSION["authenticated"]))) {
  $client_id = $ini_array["google-client-id"]["client-id"];
echo <<<EOF
<script src="https://apis.google.com/js/client:platform.js" async defer></script>
<script>
function signinCallback(authResult) {
  if (authResult['status']['signed_in']) {
    // Update the app to reflect a signed in user
    // Hide the sign-in button now that the user is authorized, for example:
    document.getElementById('signinButton').setAttribute('style', 'display: none');
    console.log(authResult['id_token']);
    document.getElementById('id_token').value = authResult['id_token'];
    document.getElementById('login').submit();
  } else {
    // Update the app to reflect a signed out user
    // Possible error values:
    //   "user_signed_out" - User is signed-out
    //   "access_denied" - User denied access to your app
    //   "immediate_failed" - Could not automatically log in the user
    console.log('Sign-in state: ' + authResult['error']);
  }
}
</script>
<div align="right" width="100%">
<span id="signinButton">
  <span
    class="g-signin"
    data-callback="signinCallback"
EOF;
  print '    data-clientid="'.$client_id.'"\n';
echo <<<EOF
    data-cookiepolicy="single_host_origin"
    data-scope="profile">
  </span>
</span>
<form id="login" method="POST" action="process_login.php">
<input type="hidden" name="id_token" id="id_token" value="none">
</form>
</div>
EOF;
}
?>
