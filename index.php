<?php
require __DIR__.'/lib/base.php';

#F3::set('CACHE',TRUE);
F3::set('DEBUG',1);
F3::set('UI','ui/');
F3::set('IMPORTS','imports/');

F3::set('FACEBOOK.client_id', '***REMOVED***');
F3::set('FACEBOOK.client_secret', '***REMOVED***');
F3::set('FACEBOOK.redirect_uri', 'http://singleyet.com/login/');
F3::set('FACEBOOK.session_key', F3::resolve('fb_{{@FACEBOOK.client_id}}_access_token'));

F3::set('DB',new DB('mysql:host=localhost;port=3306;dbname=singleyet','singleyet','***REMOVED***'));

F3::call('facebook/facebook.php');

F3::set('Facebook', new Facebook(array(
        'appId'  => F3::get('FACEBOOK.client_id'),
        'secret' => F3::get('FACEBOOK.client_secret'),
    ))
);

/////////////////////////////////////////////////
// Status Codes                                //
/////////////////////////////////////////////////
# 1 = Single
# 2 = In a relationship
# 3 = Engaged
# 4 = Married
# 5 = It's complicated
# 6 = In an open relationship
# 7 = Widowed
# 8 = Separated
# 9 = Divorced
# 10 = In a civil union
# 11 = In a domestic relationship
# 12 = Not set
/////////////////////////////////////////////////
# Single = 1, 5, 6, 7, 8, 9, 12                //
# Relationship = 2, 3, 4, 10, 11               //
/////////////////////////////////////////////////


function _get_f_list_id($uid){
    $facebook = F3::get('Facebook');
    $f_list_exists = false;

    try{
        // Try and see if they already have a "Single Yet?" friendslist
        // But maybe deleted their account from our site or somehow
        // their friendlist field went blank in the DB
        // And catch general Facebook errors...
        $f_lists = $facebook->api($uid.'/friendlists');
    } catch (FacebookApiException $e) {
        F3::error('500');
    }

    // Loop through each friendlist and
    // see if it is the Single Yet? friendlist
    foreach($f_lists['data'] as $f_list){
        if($f_list['name'] == 'Single Yet?'){
            $f_list_exists = true;
            $f_list_id = $f_list['id'];

            // If there is a friendlist for Single Yet?, break out of loop.
            break;
        }
    }

    // If the Single Yet? friendlist doesn't exist, create it.
    if(!$f_list_exists){
        $f_list = $facebook->api($uid.'/friendlists',
                                 'post',
                                 array('name' => 'Single Yet?'));
        $f_list_id = $f_list['id'];
    }

    return $f_list_id;
}

function _create_alert_message($type='alert', $message=''){
    return array('type' => $type, 'message' => $message);
}

function _force_logout(){
    session_destroy();
    F3::reroute('/');
}


F3::route('GET /',
    function() {
        $facebook = F3::get('Facebook');
        $uid = $facebook->getUser();
        // Check if they are "logged in" by session, if they arent't,
        // generate a Login URL and MAKE them sign in...
        if(!$uid){
            $scope = array(
                        'offline_access','read_friendlists','email',
                        'user_relationships','user_relationship_details',
                        'friends_relationship_details','friends_relationships',
                        'manage_friendlists','read_stream','read_friendlists'
            );
            $login_url = $facebook->getLoginUrl(array('redirect_uri' => F3::get('FACEBOOK.redirect_uri'),
                                                      'scope'  => implode(',', $scope)));
            F3::set('login_url', $login_url);

            // Load the header template
            F3::set('extra_css', array('home.css'));
            echo Template::serve('templates/header.html');

            echo F3::render('templates/index.html');

            // Load the footer template
            F3::set('extra_js', array('bootstrap-collapse.js'));
            echo Template::serve('templates/footer.html');
            die();
        }

        //If they are logged, let's render the dashboard

        // We need to store "user" for later use in the template
        // http://fatfree.sourceforge.net/page/data-mappers/beyond-crud
        $user = new Axon('user');
        $user->load(array('fb_id=:fb_id', array(':fb_id'=>$uid)));

        // They shouldn't be able to access they dashboard if they're
        // not in our database...
        if($user->dry()){
            _force_logout();
        }

        $last_login = $user->last_login;

        $notis = new Axon('notification');
        $notis = $notis->find(array('user_id=:user_id', array(':user_id'=>$user->id)), 'timestamp DESC');

        $notifications = array();
        foreach($notis as $notification){
            $is_new = FALSE;
            if($notification->timestamp > $last_login){
                $is_new = TRUE;
            }
            $n = array(
                'is_new' => $is_new,
                'fb_id' => $notification->fb_id,
                'message' => $notification->message
            );
            array_push($notifications, $n);
        }
        F3::set('notifications', $notifications);

        $js = array();

        F3::set('first_visit', FALSE);
        if($last_login == NULL){
            F3::set('first_visit', TRUE);
            array_push($js, 'bootstrap-modal.js');
            array_push($js, 'bootstrap-button.js');
            array_push($js, 'dashboard-modal.js');
        }

        $user->last_login = time();
        $user->save();

        // Make user a var for template use
        F3::set('user',
                array(
                    'fb_id' => $user->fb_id,
                    'name' => $user->name
                )
        );

        F3::set('extra_css', array('dashboard.css'));
        echo Template::serve('templates/header.html');

        F3::set('page', 'notifications');
        echo Template::serve('templates/dashboard.html');

        F3::set('extra_js', $js);
        echo Template::serve('templates/footer.html');
        die();
    }
);

F3::route('GET /newsfeed',
    function() {
        $facebook = F3::get('Facebook');
        $uid = $facebook->getUser();
        if(!$uid){
            _force_logout();
        }

        $user = new Axon('user');
        $user->load(array('fb_id=:fb_id',array(':fb_id'=>$uid)));

        if($user->dry()){
            _force_logout();
        }

        try{
            $newsfeed = $facebook->api('me/home',
                                        array(
                                            'limit' => 50,
                                            'filter' => 'fl_'.$user->fl_id
                                        )
                                    );
        } catch (FacebookApiException $e) {
            F3::error('400');
        }

        /*
        function is_relationship_story($data){
            if(array_key_exists('story', $data) && preg_match('/went from being/', $data['story'])){
                return true;
            }
            return false;
        }*/

        // Make user a var for template use
        F3::set('user',
                array(
                    'fb_id' => $user->fb_id,
                    'name' => $user->name
                )
        );


        F3::set('extra_css', array('dashboard.css'));
        echo Template::serve('templates/header.html');

        F3::set('newsfeed', $newsfeed['data']);
        F3::set('page', 'newsfeed');
        echo Template::serve('templates/newsfeed.html');

        F3::set('extra_js', array());
        echo Template::serve('templates/footer.html');

        die();
    }
);




/* Logging in and logging out ***********************************************/

F3::route('GET /login',
    function() {
        $facebook = F3::get('Facebook');
        $uid = $facebook->getUser();
        if(!$uid){
            _force_logout();
        }

        $access_token = $_SESSION[F3::get('FACEBOOK.session_key')];

        try{
            $me = $facebook->api('me');
        } catch (FacebookApiException $e) {
            F3::error('400');
        }

        $name = $me['name'];
        $email = $me['email'];

        $user = new Axon('user');
        $user->load(array('fb_id=:fb_id',array(':fb_id'=>$uid)));

        // If they aren't in our db yet, create a record
        if($user->dry()){
            $user = new Axon('user');
            $user->fb_id=$uid;
            $user->name = $name;
            $user->email = $email;
        }

        $_SESSION['f_list_existed'] = FALSE;
        // If no friends list id for "Single Yet?" in the db
        if(empty($user->fl_id)){
            $f_list_id = _get_f_list_id($uid);
            $user->fl_id = $f_list_id;
        }
        
        $user->access_token = $access_token;
        $user->save();

        F3::reroute('/');
    }
);

F3::route('GET /logout',
    function() {
        session_destroy();
        F3::reroute('/');
    }
);

/****************************************************************************/


/* Friends ******************************************************************/

F3::route('GET /friends',
    function() {
        $facebook = F3::get('Facebook');
        $uid = $facebook->getUser();
        if(!$uid){
            _force_logout();
        }

        try{
            $friends = $facebook->api('me/friends');
        } catch (FacebookApiException $e) {
            F3::error('500');
        }

        // Load the header template
        F3::set('extra_css', array('dashboard.css', 'friends.css'));
        echo Template::serve('templates/header.html');

        $user = new Axon('user');
        $user = $user->load(array('fb_id=:fb_id',array(':fb_id'=>$uid)));

        // Make user a var for template use
        F3::set('user',
                array(
                    'fb_id' => $user->fb_id,
                    'name' => $user->name
                )
        );

        $followed = new Axon('followed');
        $followed = $followed->find(array('user_id=:user_id', array(':user_id'=>$user->id)));

        $following = array();
        foreach($followed as $f){
            $following[$f->fb_id] = 1;
        }

        $i = 0;
        foreach($friends['data'] as $friend){
            $friend['is_following'] = 0;
            if(array_key_exists($friend['id'], $following)){
                $friend['is_following'] = 1;
            }
            $friends['data'][$i] = $friend;
            $i++;
        }

        #check php5.3 we can use anonymous functions
        function sortByOrder($a, $b) {
            return $b['is_following'] - $a['is_following'];
        }

        usort($friends['data'], 'sortByOrder');

        F3::set('friends', $friends['data']);
        F3::set('page', 'friends');
        echo Template::serve('templates/friends.html');

        F3::set('extra_js', array('friends.js'));
        echo Template::serve('templates/footer.html');
        die();
    }
);

F3::route('POST /friends/add',
    function() {
        $facebook = F3::get('Facebook');
        $uid = $facebook->getUser();
        if(!$uid){
            _force_logout();
        }

        // The ID of the friend they want to add
        $id = F3::get('POST.id');
        if(!isset($id) || empty($id)){
            F3::error('400');
        }

        // Grab user for Friendlist adding
        $user = new Axon('user');
        $user->load(array('fb_id=:fb_id',array(':fb_id'=>$uid)));

        if($user->dry()){
            _force_logout();
        }

        try{
            $info = $facebook->api('/'.$id);
            $add_to_fl = $facebook->api($user->fl_id.'/members/'.$id, 'POST');
        } catch (FacebookApiException $e) {
            $f_list_id = _get_f_list_id($uid);
            $user->fl_id = $f_list_id;
            $user->save();

            try{
                $info = $facebook->api('/'.$id);
                $add_to_fl = $facebook->api($user->fl_id.'/members/'.$id, 'POST');
            } catch (FacebookApiException $e) {
                F3::error('500');
            }
        }

        /* Do DB look up for rel_status after the friend info
        // request is successful ***************************/

        // Grab the rel_status from the db and put them into
        // an array for comparing later
        $rel_status_ids = new Axon('rel_status');
        $rel_status_ids = $rel_status_ids->afind();

        $rel_ids = array();
        foreach($rel_status_ids as $result){
            $rel_ids[$result['name']] = $result['id'];
        }
        /***************************************************/

        if(!isset($info['relationship_status'])){
            $relationship_status = 'Not set';
        } else {
            $relationship_status = $info['relationship_status'];
        }

        $followed = new Axon('followed');
        $followed->fb_id = (string) $info['id'];
        $followed->rel_status_id = $rel_ids[$relationship_status];
        $followed->user_id = $user->id;
        $followed->save();

        die(json_encode(
                array(
                    'success' => true,
                    'result' => array(
                        'name' => $info['name']
                    )
                )
            )
        );
    }
);

F3::route('POST /friends/remove',
    function() {
        $facebook = F3::get('Facebook');
        $uid = $facebook->getUser();
        if(!$uid){
            _force_logout();
        }

        // The ID of the friend they want to add
        $id = F3::get('POST.id');
        if(!isset($id) || empty($id)){
            F3::error('400');
        }

        $user = new Axon('user');
        $user->load(array('fb_id=:fb_id',array(':fb_id'=>$uid)));

        if($user->dry()){
            _force_logout();
        }

        try{
            $rsp = $facebook->api($user->fl_id.'/members/'.$id, 'DELETE');
        } catch (FacebookApiException $e) {
            // For some reason, this is the error Facebook throws if a
            // Friendlist does not exist. Idiots...
            if($e != 'OAuthException: (#297) Requires extended permission: manage_friendlists'){
                F3::error('500');
            } else {
                $user->fl_id = '';
                $user->save();
            }
        }

        $followed = new Axon('followed');
        $followed->load(
            array(
                'fb_id=:fb_id AND user_id=:user_id',
                array(':fb_id'=>$id, ':user_id'=>$user->id)
            )
        );
        $followed->erase();

        die(json_encode(array('success' => true)));
    }
);

/****************************************************************************/




/* Ajax Feeds ***************************************************************/


/****************************************************************************/


/* Settings *****************************************************************/
F3::route('GET /settings',
    function() {
        $facebook = F3::get('Facebook');
        $uid = $facebook->getUser();
        if(!$uid){
            _force_logout();
        }

        $user = new Axon('user');
        $user->load(array('fb_id=:fb_id',array(':fb_id'=>$uid)));

        if($user->dry()){
            _force_logout();
        }

        // Make user a var for template use
        F3::set('user',
                array(
                    'fb_id' => $user->fb_id,
                    'name' => $user->name,
                    'email_opt' => $user->email_opt
                )
        );

        if(isset($_SESSION['message'])){
            F3::set('message', $_SESSION['message']);
            F3::set('extra_js', array('bootstrap-alert.js'));
            unset($_SESSION['message']);
        }

        F3::set('extra_css', array('settings.css'));
        echo Template::serve('templates/header.html');

        F3::set('page', 'general_settings');
        echo Template::serve('templates/settings.html');

        echo Template::serve('templates/footer.html');

        die();
    }
);

F3::route('POST /settings/save',
    function() {
        $facebook = F3::get('Facebook');
        $uid = $facebook->getUser();
        if(!$uid){
            _force_logout();
        }

        $user = new Axon('user');
        $user->load(array('fb_id=:fb_id',array(':fb_id'=>$uid)));

        if($user->dry()){
            _force_logout();
        }

        $email_opt = (F3::get('POST.email_opt') == 'on') ? TRUE : False;
        $user->email_opt = $email_opt;

        $user->save();

        $_SESSION['message'] = _create_alert_message('alert-success', 'Settings updated successfully!');
        F3::reroute('/settings/');
    }
);
/****************************************************************************/



F3::run();