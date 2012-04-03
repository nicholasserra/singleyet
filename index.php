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

#session_destroy();

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
        F3::set('user', new Axon('user'));
        F3::get('user')->load(array('fb_id=:fb_id',array(':fb_id'=>$uid)));

        // They shouldn't be able to access they dashboard if they're
        // not in our database...
        if(F3::get('user')->dry()){
            F3::error('403');
        }

        F3::set('extra_css', array('dashboard.css'));
        echo Template::serve('templates/header.html');

        if(isset($_SESSION['f_list_existed'])){
            F3::set('alert', array(
                'type' => 'alert-error',
                'header' => 'A "Single Yet?" Friend List already existed!',
                'message' => 'If you would like to sync your Friend List, <a href="/settings/sync/friendlist">Click Here</a>'
            ));
        }
        unset($_SESSION['f_list_existed']);
        echo Template::serve('templates/dashboard.html');

        F3::set('extra_js', array('bootstrap-dropdown.js',
                                  'dashboard.js'));
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
            F3::error('403');
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
            $user=new Axon('user');
            $user->fb_id=$uid;
            $user->name = $name;
            $user->email = $email;
        }

        $_SESSION['f_list_existed'] = FALSE;
        // If no friends list id for "Single Yet?" in the db
        if(empty($user->fl_id)){
            $f_list_exists = false;

            try{
                // Try and see if they already have a "Single Yet?" friendslist
                // But maybe deleted their account from our site or somehow
                // their friendlist field went blank in the DB
                // And catch general Facebook errors...
                $f_lists = $facebook->api($uid.'/friendlists');
            } catch (FacebookApiException $e) {
                die('Error!');
            }

            // Loop through each friendlist and
            // see if it is the Single Yet? friendlist
            foreach($f_lists['data'] as $f_list){
                if($f_list['name'] == 'Single Yet?'){
                    $f_list_exists = true;
                    $f_list_id = $f_list['id'];
                    $_SESSION['f_list_existed'] = TRUE;
                    // If there is a friendlist for Single Yet?, break out of loop.
                    break;
                }
            }
    
            // If the Single Yet? friendlist doesn't exist, create it.
            if(!$f_list_exists){
                $f_list = $facebook->api($uid.'/friendlists',
                                         'post',
                                         array('name' => 'Single Yet?'));
                $f_list_id = $list['id'];
            }
            
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



F3::route('GET /friends',
    function() {
        $facebook = F3::get('Facebook');
        $uid = $facebook->getUser();
        if(!$uid){
            F3::error('403');
        }

        if(F3::exists('GET.offset')){
            $offset = F3::get('GET.offset');
        } else {
            $offset = 0;
        }

        try{
            $friends = $facebook->api('me/friends',
                                       array(
                                            'limit' => 100,
                                            'offset' => $offset
                                       )
                                  );
        } catch (FacebookApiException $e) {
            F3::error('500');
        }

        // Load the header template
        F3::set('extra_css', array('dashboard.css'));
        echo Template::serve('templates/header.html');

        F3::set('friends', $friends['data']);
        echo Template::serve('templates/ajax/friends.html');

        // Load the footer template
        F3::set('extra_js', array('bootstrap-collapse.js'));
        echo Template::serve('templates/footer.html');
        die();
    }
);



/* Ajax Feeds ***************************************************************/

F3::route('GET /ajax/newsfeed',
    function() {
        $facebook = F3::get('Facebook');
        $uid = $facebook->getUser();
        if(!$uid){
            F3::error('403');
        }

        $user=new Axon('user');
        $user->load(array('fb_id=:fb_id',array(':fb_id'=>$uid)));

        if($user->dry()){
            F3::error('403');
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

        F3::set('newsfeed', $newsfeed['data']);
        $html = Template::serve('templates/ajax/newsfeed.html');
        
        die(json_encode(array('success' => true,
                              'result' => array('html' => $html)
                        )
            )
        );
    }
);
/****************************************************************************/



F3::run();