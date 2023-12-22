  <?php if(!in_array($user->data()->id,$master_account)){ Redirect::to($us_url_root.'users/admin.php');} //only allow master accounts to manage plugins! ?>

<?php
include "plugin_info.php";
pluginActive($plugin_name);
 if(!empty($_POST)){
   if(!Token::check(Input::get('csrf'))){
     include($abs_us_root.$us_url_root.'usersc/scripts/token_error.php');
   }
   // Redirect::to('admin.php?err=I+agree!!!');
 }

$throttle_settings = json_decode($settings->throttle, true) ;
?>

<div class="content mt-3">
 		<div class="row">
 			<div class="col-12">
          <a href="<?=$us_url_root?>users/admin.php?view=plugins">Return to the Plugin Manager</a>
 					<h1>Configure the Throttle</h1>
					<?php echo ThrottleForm($throttle_settings) ; ?>
 			</div>
 		</div>


    <!-- Do not close the content mt-3 div in this file -->
