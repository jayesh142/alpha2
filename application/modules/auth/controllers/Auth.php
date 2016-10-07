<?php defined('BASEPATH') OR exit('No direct script access allowed');
class Auth extends CI_Controller {
	function __construct()
	{
		parent::__construct();
		$this->form_validation->set_error_delimiters($this->config->item('error_start_delimiter', 'ion_auth'), $this->config->item('error_end_delimiter', 'ion_auth'));
        $this->lang->load(array('auth','frontpage','dashboard/front'));
        $this->load->model('dashboard/home_m','home');
        
     	// set create post hook
        $event  =   'post_register';
        $name   =   'first_Ocrim';
        $class  =   $this; 
        $method =   'startgeld';
        $args   =   array('10');  	
    	$this->ion_auth->set_hook($event, $name, $class, $method, $args); 
        $this->ion_auth->set_hook('post_login_successful','daily_ocrim',$this,'tagesgeld',array(''));      
	}




public function tagesgeld($value)
    {  //$data='yes';
        $this->load->model('Auth_users_m','auth_users');
        $this->load->model('Points_model','points');
        
        $user   =   $this->ion_auth->user()->row();
        // war der user das letzte Mal an einem vorherigen kalendertag eingeloggt?
        if($this->auth_users->another_day_logged($user->id))
            {   //wenn ja: schau nach dem Tagesgeld-Wert und schreib ihn ihm gut 
                if($this->points->set_daily_ocrim($user->id))
                    {   // Nach der Gutschrift trage das neue "letzte Besuchstdatum ein"
                        $this->auth_users->set_rewarded_login_date($user->id);
                        $_SESSION['successmessage'] = $this->lang->line('daily_ocrim_success');
                        $this->session->mark_as_flash('successmessage');
                    }
                else
                    {   // dann stimmt etwas nicht. 
                        $_SESSION['errormessage'] = $this->lang->line('daily_ocrim_error');
                        $this->session->mark_as_flash('errormessage');
                        }
            }
    } 

public function startgeld($value)
    {
        $this->load->model('Games_model','games');
        $value  = $this->games->get_settings('firstocrim');
        $id     = $_SESSION['timer'];
        $data   = array(    'user_id'=>$id,
                            'credits'=>'0',
                            'ocrim'=>$value,
                            'indicator'=>'+',
                            'value'=>$value,
                            'type'=>'ocrim',
                            'description'=>'account_created',
                            'created_at'=>date("Y-m-d H:i:s", time()),
                            'created_by'=>'0',
                            'updated_by'=>'0');
        $this->db->insert('game_points',$data);
        return true;
    } 

function register()
    {
        if ($this->ion_auth->logged_in())
            {
                redirect('auth', 'refresh'); 
            }
        $tables                         =   $this->config->item('tables','ion_auth');
        $identity_column                =   $this->config->item('identity','ion_auth');
        $this->data['identity_column']  =   $identity_column;
        // validate form input
        if($identity_column!=='email')
        {
            $this->form_validation->set_rules('username', 'Username', 'required|is_unique[' . $tables['users'] . '.username]|alpha_dash', $this->lang->line('username_exists'));
            $this->form_validation->set_rules('email', $this->lang->line('create_user_validation_email_label'), 'required|valid_email', $this->lang->line('email_exists'));
        }
        else
        {
            $this->form_validation->set_rules('email', $this->lang->line('create_user_validation_email_label'), 'required|valid_email|is_unique[' . $tables['users'] . '.email]', $this->lang->line('email_exists'));
        }       
        $this->form_validation->set_rules('password', $this->lang->line('create_user_validation_password_label'), 'required|min_length[' . $this->config->item('min_password_length', 'ion_auth') . ']|max_length[' . $this->config->item('max_password_length', 'ion_auth') . ']|matches[password_confirm]');
        $this->form_validation->set_rules('password_confirm', $this->lang->line('create_user_validation_password_confirm_label'), 'required');
        if ($this->form_validation->run() == true)
        {
            $email    = strtolower($this->input->post('email'));
            $identity = ($identity_column==='email') ? $email : $this->input->post('username');
            $password = $this->input->post('password');
            $additional_data = array('username'=>$this->input->post('username'),'gender'=>$this->input->post('gender'),'birthday'=>$this->input->post('birthday'));

           	// user needs some money to play
            $this->ion_auth->set_hook('post_register', 'first_Ocrim', $this, 'startgeld', array('10')); 
        }
        if($this->form_validation->run() == true && $this->ion_auth->register($identity, $password, $email, $additional_data))
        {
            $_SESSION['successmessage'] = 'Wir haben Dir gerade eine E-Mail mit einem Best&auml;tigungslink zugesandt. Bitte klicke den Link, zur Best&auml;tigung dass Deine E-Mail-Adresse echt ist, einmal an. Danke!';
            $this->session->mark_as_flash('successmessage');
            redirect(base_url('auth'), 'refresh');
        }
        else
        {
            // Back error message
            
            $_SESSION['gender']         =   $this->input->post('gender'); $this->session->mark_as_flash('gender');
            $_SESSION['birthday']       =   $this->input->post('birthday'); $this->session->mark_as_flash('birthday');
            $_SESSION['username']       =   $this->input->post('username'); $this->session->mark_as_flash('username');
            $_SESSION['email']          =   $this->input->post('email'); $this->session->mark_as_flash('email');
            $_SESSION['errormessage']   =   validation_errors(); $this->session->mark_as_flash('errormessage');
            // back to user form
            redirect(base_url('auth'),'refresh');
        }        
    }

	// edit a user
function profile()
	{   //(isset($_POST)){$this->data['post']=$_POST;}
        if (!$this->ion_auth->logged_in()){redirect(base_url(), 'refresh');}
	   $id   = $this->session->userdata('user_id');
        // image 
        if($this->input->post('upload'))
            {
            $this->load->model('media_m');
            $config['upload_path'] = $path = '/kunden/496585_40789/webseiten/test/assets/img/profiles/';
           // $config['upload_path'] = $path = 'assets/img/profiles/'; //var_dump($config['upload_path']); exit;
            $config['allowed_types'] = 'gif|jpg|png|jpeg';
            $config['max_size'] = 250;
            $this->load->library('upload', $config);
             if ($this->upload->do_upload('userfile'))
                {
                    $upload   = $this->upload->data();
                    $data['image']  = $file_name =   $upload['file_name'];
                    $this->db->where('id',$id)->update('auth_users',$data);
                    $this->media_m->resize_and_crop($file_name, $path);
                    redirect(base_url('auth/profile'),'refresh');
                }
            }
        // if cancel zur einfachen displayansicht
        if($this->input->post('modus')=='cancel'){
            $_SESSION['modus']='display'; $this->session->mark_as_flash('modus');
            redirect(base_url('auth/profile'),'refresh');
            }
       // is it display or edit mode for the view ?
        if($this->input->post('modus')){$_SESSION['modus'] = $this->input->post('modus'); $this->session->mark_as_flash('modus');}
        if(isset($_SESSION['modus']) && $_SESSION['modus']=='edit'){$this->data['modus']='edit';}else{$this->data['modus']='display';}
	   //
       	$this->load->model('design_m');
        $tables                         =   $this->config->item('tables','ion_auth');

		$user = $this->ion_auth->user($id)->row();
        // validate form input
		//$this->form_validation->set_rules('username', 'Username', 'required|callback_usernameExist|alpha_dash');
        $this->form_validation->set_rules('first_name', $this->lang->line('edit_user_validation_fname_label'), 'required');
		$this->form_validation->set_rules('last_name', $this->lang->line('edit_user_validation_lname_label'), 'required');
		$this->form_validation->set_rules('gender', $this->lang->line('gender'), 'required');
		//$this->form_validation->set_rules('email', $this->lang->line('edit_user_email_label'), 'required');
        // additional form validation
		if (isset($_POST) && !empty($_POST))
		{   
            // CSRF Schlüssel ?
			if ($this->_valid_csrf_nonce() === FALSE || $id != $this->input->post('id')){
				show_error($this->lang->line('error_csrf'));
                redirect(base_url('auth/profile'),'refresh');
                }
			// Ergänzung wenn Passwortänderung
			if ($this->input->post('password') && ($this->input->post('password')!='')){
				$this->form_validation->set_rules('password', $this->lang->line('edit_user_validation_password_label'), 'required|min_length[' . $this->config->item('min_password_length', 'ion_auth') . ']|max_length[' . $this->config->item('max_password_length', 'ion_auth') . ']|matches[password_confirm]');
				$this->form_validation->set_rules('password_confirm', $this->lang->line('edit_user_validation_password_confirm_label'), 'required');
                }
		}
        if ($this->form_validation->run() === TRUE)
			{   $this->data['modus']='display';
				$data = array(
					'first_name' => $this->input->post('first_name'),
					'last_name'  => $this->input->post('last_name'),
					//'username'    => $this->input->post('username'),
					'gender'    => $this->input->post('gender'),
					//'email'      => $this->input->post('email'),
					'birthday'      => $this->input->post('birthday')
				);
				// update the password if it was posted
				if ($this->input->post('password') && ($this->input->post('password')!=''))
				{
					$data['password'] = $this->input->post('password');
				}
				// Only allow updating groups if user is admin
				if ($this->ion_auth->is_admin())
				{
					//Update the groups user belongs to
					$groupData = $this->input->post('groups');
					if (isset($groupData) && !empty($groupData)) {
						$this->ion_auth->remove_from_group('', $id);
						foreach ($groupData as $grp) {
							$this->ion_auth->add_to_group($grp, $id);
						}
					}
				}
			// check to see if we are updating the user
			   if($this->ion_auth->update($user->id, $data))
			    {// redirect them back to the admin page if admin, or to the base url if non admin
				    $this->session->set_flashdata('errormessage', $this->ion_auth->messages() );
				    if ($this->ion_auth->is_admin()){   redirect(base_url('auth/profile'), 'refresh');}
                        else{                           redirect(base_url('auth/profile'), 'refresh');}
			    }else
			    {// redirect them back to the admin page if admin, or to the base url if non admin
				    $this->session->set_flashdata('errormessage', $this->ion_auth->errors() );
				    if ($this->ion_auth->is_admin()){   redirect(base_url('auth/profile'), 'refresh');}
                        else{                           redirect(base_url('auth/profile'), 'refresh');}
			    }
			}
        else // FORM VALIDATION FAILED
        {   // display the edit user form
            $this->data['csrf'] = $this->_get_csrf_nonce();
    		// set the flash data error message if there is one
            if(isset($_SESSION['errormessage'])){ $data['errormessage'] = $this->design_m->message('error',$_SESSION['errormessage']); }
            if(isset($_SESSION['successmessage'])){ $data['successmessage'] = $this->design_m->message('success',$_SESSION['successmessage']); }
            if($this->input->post('update')!='false'){
                $this->data['errormessage'] = (validation_errors() ? validation_errors() : ($this->ion_auth->errors() ? $this->ion_auth->errors() : $this->session->flashdata('errormessage')));
                }
            // pass the user to the view
            $this->data['user'] = array('username'=>$user->username,
                                        'last_name'=>$user->last_name,
                                        'first_name'=>$user->first_name,
                                        'birthday'=>date('d. M.Y',strtotime($user->birthday)),
                                        'email'=>$user->email,
                                        'id'=>$user->id,
                                        'image'=>$user->image
                                        );
            if($user->gender == 'f'){$this->data['user']['gender']=$this->lang->line('female');}elseif($user->gender == 'm'){$this->data['user']['gender']=$this->lang->line('male');}else{$this->data['user']['gender']='';}
            $this->data['first_name'] = array(  'name'  => 'first_name',
                                    			'id'    => 'first_name',
                                    			'type'  => 'text',
                                    			'value' => $this->form_validation->set_value('first_name', $user->first_name),
                                                'class' => 'col-md-12'
                                                );
            $this->data['last_name'] = array(   'name'  => 'last_name',
                                    			'id'    => 'last_name',
                                    			'type'  => 'text',
                                    			'value' => $this->form_validation->set_value('last_name', $user->last_name),
                                                'class' => 'col-md-12'
                                                );
            $this->data['user_name'] = array(   'name'  => 'username',
                                    			'id'    => 'username',
                                    			'type'  => 'text',
                                    			'value' => $user->username,
                                                'class' => 'col-md-12',
                                                'readonly'=>'true',
                                                'title' =>'nicht &auml;nderbar'
                                                );
            $this->data['birthday'] = array(    'name'  => 'birthday',
                                    			'id'    => 'birthday',
                                    			'type'  => 'date',
                                    			'value' => $this->form_validation->set_value('birthday', $user->birthday),
                                                'class' => 'col-md-12'
                                                );
            $this->data['email'] = array(       'name'  => 'email',
                                    			'id'    => 'email',
                                    			'type'  => 'text',
                                    			'value' => $this->form_validation->set_value('email', $user->email),
                                                'class' => 'col-md-12',
                                                'readonly'=>'true',
                                                'title' =>'nicht &auml;nderbar'
                                                );
            $this->data['password'] = array(    'name' => 'password',
                                    			'id'   => 'password',
                                    			'type' => 'password',
                                                'class' => 'col-md-12'
                                                );
            $this->data['password_confirm'] = array('name' => 'password_confirm',
                                    			'id'   => 'password_confirm',
                                    			'type' => 'password',
                                                'class' => 'col-md-12'
                                                );
            $this->data['gender']       =   $this->design_m->myDropdown_no_label('gender', array('f'=>'female','m'=>'male'), 'f',$user->gender);
            $this->data['title']        =   $this->lang->line('my_profile');
            $this->data['subheading']   =   $this->lang->line('edit_your_profile');
            $this->data['submit_btn']   =   $this->lang->line('Save');
            $this->data['username']     =   $user->username;
            $this->data['avatar']       =   profile_img($user->image);
            $this->data['footer']       =   $this->home->page('Footer');
            $this->data['action']       =   base_url('auth/image');
            // call view
            $this->data['view']         =   'auth/profile';
            //$this->data['dati'] = $this->data;
            $this->load->view('backend',$this->data); 
        }
	}

function users()
	{
		if (!$this->ion_auth->logged_in())
		{
			// redirect them to the login page
			redirect('auth', 'refresh');
		}
		elseif (!$this->ion_auth->is_admin()) // remove this elseif if you want to enable this for non-admins
		{
			// redirect them to the home page because they must be an administrator to view this
			return show_error('You must be an administrator to view this page.');
		}
		else
		{     
        $user = $this->ion_auth->user()->row();
        $this->data['avatar']   =   profile_img($user->image);   
        $this->data['username']  =   $user->first_name;
			// set the flash data error message if there is one
			$this->data['message'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('message');
			//list the users
			$this->data['users'] = $this->ion_auth->users()->result();
			foreach ($this->data['users'] as $k => $user)
			{
				$this->data['users'][$k]->groups = $this->ion_auth->get_users_groups($user->id)->result();
			}
			$this->data['view']    =   'auth/index';
            $this->data['footer']     =   $this->home->page('Footer'); 
            $this->load->view('backend',$this->data);
		}
	} 
    
function index()
	{
		if ($this->ion_auth->logged_in())
		{
			// redirect them to the login page
			redirect('dashboard', 'refresh');
		}
        $this->load->library('recaptcha');
        $this->load->model('dashboard/home_m','home');
        $this->load->model('design_m','');
		$this->data['title'] = $this->lang->line('login_heading');
		//validate form input
		$this->form_validation->set_rules('identity', str_replace(':', '', $this->lang->line('login_identity_label')), 'required');
		$this->form_validation->set_rules('password', str_replace(':', '', $this->lang->line('login_password_label')), 'required');
		if ($this->form_validation->run() == true)
		{
            // Catch the user's answer
            $captcha_answer = $this->input->post('g-recaptcha-response');
            
            // Verify user's answer
            $response = $this->recaptcha->verifyResponse($captcha_answer);
            
			// check to see if the user is logging in
			// check for "remember me"
			$remember = (bool) $this->input->post('remember');

			if ($this->ion_auth->login($this->input->post('identity'), $this->input->post('password'), $remember)&&$response['success'])
			{
				//if the login is successful

				//redirect them back to the home page
				$this->session->set_flashdata('message', $this->ion_auth->messages());                                                              
                redirect('dashboard', 'refresh');
			}
			else
			{
				// if the login was un-successful
				// redirect them back to the login page
				$this->session->set_flashdata('message', $this->ion_auth->errors());
				redirect('auth', 'refresh'); // use redirects instead of loading views for compatibility with MY_Controller libraries
			}
		}
		else
		{
			// the user is not logging in so display the login page
			// set the flash data error message if there is one
			$this->data['message'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('message');
			$this->data['identity'] = array('name' => 'identity',
				'id'    => 'identity',
				'type'  => 'text',
				'value' => $this->form_validation->set_value('identity'),
                'class' => 'form-control'
			);
			$this->data['password'] = array('name' => 'password',
				'id'   => 'password',
				'type' => 'password',
                'class' => 'form-control'
			);
            if(isset($_SESSION['email'])){ $this->data['email'] = $_SESSION['email']; }
            if(isset($_SESSION['username'])){ $this->data['username'] = $_SESSION['username']; }
            if(isset($_SESSION['anrede'])){ $this->data['anrede'] = $_SESSION['anrede']; }
            if(isset($_SESSION['birthday'])){ $this->data['birthday'] = $_SESSION['birthday']; }
            if(isset($_SESSION['errormessage'])){ $this->data['errormessage'] = $this->design_m->message('error',$_SESSION['errormessage']); }
            if(isset($_SESSION['successmessage'])){ $this->data['successmessage'] = $this->design_m->message('success',$_SESSION['successmessage']); }
            $this->data['sexDropdown']          =   $this->design_m->myDropdown('gender',array('male'=>'Herr','female'=>'Frau'),'f'); 
            $this->data['csrf']                 =   array('name' => $this->security->get_csrf_token_name(),'hash' => $this->security->get_csrf_hash());
            $this->data['title_login']          =   $this->lang->line('title_login'); 
            $this->data['title_register']       =   $this->lang->line('title_register');
            $this->data['subtitle_login']       =   $this->lang->line('subtitle_login'); 
            $this->data['subtitle_register']    =   $this->lang->line('subtitle_register');
            $this->data['footer']               =   $this->home->page('Footer'); 
            $this->data['view']                 =   'auth/register'; //login';
            $this->load->view('frontpage',$this->data);
		}
	}
	// log the user out
    
function logout()
	{
		$this->data['title'] = "Logout";
		// log the user out
		$logout = $this->ion_auth->logout();
		// redirect them to the login page
		$this->session->set_flashdata('message', $this->ion_auth->messages());
		redirect(base_url(), 'refresh');
	}
	// change password
function change_password()
	{
		$this->form_validation->set_rules('old', $this->lang->line('change_password_validation_old_password_label'), 'required');
		$this->form_validation->set_rules('new', $this->lang->line('change_password_validation_new_password_label'), 'required|min_length[' . $this->config->item('min_password_length', 'ion_auth') . ']|max_length[' . $this->config->item('max_password_length', 'ion_auth') . ']|matches[new_confirm]');
		$this->form_validation->set_rules('new_confirm', $this->lang->line('change_password_validation_new_password_confirm_label'), 'required');
		if (!$this->ion_auth->logged_in())
		{
			redirect(base_url('auth'), 'refresh');
		}
		$user = $this->ion_auth->user()->row();
		if ($this->form_validation->run() == false)
		{
			// display the form
			// set the flash data error message if there is one
			$this->data['message'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('message');
			$this->data['min_password_length'] = $this->config->item('min_password_length', 'ion_auth');
			$this->data['old_password'] = array(
				'name' => 'old',
				'id'   => 'old',
				'type' => 'password',
			);
			$this->data['new_password'] = array(
				'name'    => 'new',
				'id'      => 'new',
				'type'    => 'password',
				'pattern' => '^.{'.$this->data['min_password_length'].'}.*$',
			);
			$this->data['new_password_confirm'] = array(
				'name'    => 'new_confirm',
				'id'      => 'new_confirm',
				'type'    => 'password',
				'pattern' => '^.{'.$this->data['min_password_length'].'}.*$',
			);
			$this->data['user_id'] = array(
				'name'  => 'user_id',
				'id'    => 'user_id',
				'type'  => 'hidden',
				'value' => $user->id,
			);
			// render
			
            $this->data['view']    =   'auth/change_password';
            $this->data['footer']     =   $this->home->page('Footer'); 
            $this->load->view('frontpage',$this->data);
		}
		else
		{
			$identity = $this->session->userdata('identity');
			$change = $this->ion_auth->change_password($identity, $this->input->post('old'), $this->input->post('new'));
			if ($change)
			{
				//if the password was successfully changed
				$this->session->set_flashdata('message', $this->ion_auth->messages());
				$this->logout();
			}
			else
			{
				$this->session->set_flashdata('message', $this->ion_auth->errors());
				redirect('auth/change_password', 'refresh');
			}
		}
	}
	// forgot password
function forgot_password()
	{
		// setting validation rules by checking whether identity is username or email
		if($this->config->item('identity', 'ion_auth') != 'email' )
		{
		   $this->form_validation->set_rules('identity', $this->lang->line('forgot_password_identity_label'), 'required');
		}
		else
		{
		   $this->form_validation->set_rules('identity', $this->lang->line('forgot_password_validation_email_label'), 'required|valid_email');
		}
		if ($this->form_validation->run() == false)
		{
			$this->data['type'] = $this->config->item('identity','ion_auth');
			// setup the input
			$this->data['identity'] = array('name' => 'identity',
				'id' => 'identity',
			);
			if ( $this->config->item('identity', 'ion_auth') != 'email' ){
				$this->data['identity_label'] = $this->lang->line('forgot_password_identity_label');
			}
			else
			{
				$this->data['identity_label'] = $this->lang->line('forgot_password_email_identity_label');
			}
			// set any errors and display the form
			$this->data['message'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('message');
			$this->data['view']    =   'auth/forgot_password';
            $this->data['footer']     =   $this->home->page('Footer'); 
            $this->load->view('frontpage',$this->data);
		}
		else
		{
			$identity_column = $this->config->item('identity','ion_auth');
			$identity = $this->ion_auth->where($identity_column, $this->input->post('identity'))->users()->row();
			if(empty($identity)) {
	            		if($this->config->item('identity', 'ion_auth') != 'email')
		            	{
		            		$this->ion_auth->set_error('forgot_password_identity_not_found');
		            	}
		            	else
		            	{
		            	   $this->ion_auth->set_error('forgot_password_email_not_found');
		            	}
		                $this->session->set_flashdata('message', $this->ion_auth->errors());
                		redirect("auth/forgot_password", 'refresh');
            		}
			// run the forgotten password method to email an activation code to the user
			$forgotten = $this->ion_auth->forgotten_password($identity->{$this->config->item('identity', 'ion_auth')});
			if ($forgotten)
			{
				// if there were no errors
				$this->session->set_flashdata('message', $this->ion_auth->messages());
                $_SESSION['successmessage'] = 'Wir haben Dir gerade eine E-Mail mit einem Aktivierungslinklink zugesandt.';
                $this->session->mark_as_flash('successmessage');
				redirect(base_url('auth'), 'refresh'); //we should display a confirmation page here instead of the login page
			}
			else
			{
				$this->session->set_flashdata('message', $this->ion_auth->errors());
				redirect("auth/forgot_password", 'refresh');
			}
		}
	}
	// reset password - final step for forgotten password
public function reset_password($code = NULL)
	{
		if (!$code)
		{
			show_404();
		}
		$user = $this->ion_auth->forgotten_password_check($code);
		if ($user)
		{
			// if the code is valid then display the password reset form
			$this->form_validation->set_rules('new', $this->lang->line('reset_password_validation_new_password_label'), 'required|min_length[' . $this->config->item('min_password_length', 'ion_auth') . ']|max_length[' . $this->config->item('max_password_length', 'ion_auth') . ']|matches[new_confirm]');
			$this->form_validation->set_rules('new_confirm', $this->lang->line('reset_password_validation_new_password_confirm_label'), 'required');
			if ($this->form_validation->run() == false)
			{
				// display the form
				// set the flash data error message if there is one
				$this->data['message'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('message');
				$this->data['min_password_length'] = $this->config->item('min_password_length', 'ion_auth');
				$this->data['new_password'] = array(
					'name' => 'new',
					'id'   => 'new',
					'type' => 'password',
					'pattern' => '^.{'.$this->data['min_password_length'].'}.*$',
				);
				$this->data['new_password_confirm'] = array(
					'name'    => 'new_confirm',
					'id'      => 'new_confirm',
					'type'    => 'password',
					'pattern' => '^.{'.$this->data['min_password_length'].'}.*$',
				);
				$this->data['user_id'] = array(
					'name'  => 'user_id',
					'id'    => 'user_id',
					'type'  => 'hidden',
					'value' => $user->id,
				);
				$this->data['csrf'] = $this->_get_csrf_nonce();
				$this->data['code'] = $code;
				// render
    			$this->data['view']    =   'auth/reset_password';
            $this->data['footer']     =   $this->home->page('Footer'); 
                $this->load->view('frontpage',$this->data);                
			}
			else
			{
				// do we have a valid request?
				if ($this->_valid_csrf_nonce() === FALSE || $user->id != $this->input->post('user_id'))
				{
					// something fishy might be up
					$this->ion_auth->clear_forgotten_password_code($code);
					show_error($this->lang->line('error_csrf'));
				}
				else
				{
					// finally change the password
					$identity = $user->{$this->config->item('identity', 'ion_auth')};
					$change = $this->ion_auth->reset_password($identity, $this->input->post('new'));
					if ($change)
					{
						// if the password was successfully changed
						$this->session->set_flashdata('message', $this->ion_auth->messages());
						redirect("auth/login", 'refresh');
					}
					else
					{
						$this->session->set_flashdata('message', $this->ion_auth->errors());
						redirect('auth/reset_password/' . $code, 'refresh');
					}
				}
			}
		}
		else
		{
			// if the code is invalid then send them back to the forgot password page
			$this->session->set_flashdata('message', $this->ion_auth->errors());
			redirect("auth/forgot_password", 'refresh');
		}
	}
	// activate the user
function activate($id, $code=false)
	{
		if ($code !== false)
		{
			$activation = $this->ion_auth->activate($id, $code);
		}
		else if ($this->ion_auth->is_admin())
		{
			$activation = $this->ion_auth->activate($id);
		}
		if ($activation)
		{
			// redirect them to the auth page
			$this->session->set_flashdata('message', $this->ion_auth->messages());
			redirect(base_url('auth'), 'refresh');
		}
		else
		{
			// redirect them to the forgot password page
			$this->session->set_flashdata('message', $this->ion_auth->errors());
			redirect("auth/forgot_password", 'refresh');
		}
	}
	// deactivate the user
function deactivate($id = NULL)
	{
		if (!$this->ion_auth->logged_in() || !$this->ion_auth->is_admin())
		{
			// redirect them to the home page because they must be an administrator to view this
			return show_error('You must be an administrator to view this page.');
		}
		$id = (int) $id;
		$this->load->library('form_validation');
		$this->form_validation->set_rules('confirm', $this->lang->line('deactivate_validation_confirm_label'), 'required');
		$this->form_validation->set_rules('id', $this->lang->line('deactivate_validation_user_id_label'), 'required|alpha_numeric');
		if ($this->form_validation->run() == FALSE)
		{
			// insert csrf check
			$this->data['csrf'] = $this->_get_csrf_nonce();
			$this->data['user'] = $this->ion_auth->user($id)->row();
            $user = $this->ion_auth->user()->row();
            $this->data['username']  =   $user->username;
    
			$this->data['view']    =   'auth/deactivate_user';
            $this->data['footer']     =   $this->home->page('Footer'); 
            $this->load->view('backend',$this->data);  

		}
		else
		{
			// do we really want to deactivate?
			if ($this->input->post('confirm') == 'yes')
			{
				// do we have a valid request?
				if ($this->_valid_csrf_nonce() === FALSE || $id != $this->input->post('id'))
				{
					show_error($this->lang->line('error_csrf'));
				}
				// do we have the right userlevel?
				if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin())
				{
					$this->ion_auth->deactivate($id);
				}
			}
			// redirect them back to the auth page
			redirect('auth', 'refresh');
		}
	}
	// create a new user
function create_user()
    {
        $this->data['title'] = $this->lang->line('create_user_heading');
        if (!$this->ion_auth->logged_in() || !$this->ion_auth->is_admin())
        {
            redirect('auth', 'refresh'); 
        }
        $tables = $this->config->item('tables','ion_auth');
        $identity_column = $this->config->item('identity','ion_auth');
        $this->data['identity_column'] = $identity_column;
        // validate form input
        $this->form_validation->set_rules('first_name', $this->lang->line('create_user_validation_fname_label'), 'required');
        $this->form_validation->set_rules('last_name', $this->lang->line('create_user_validation_lname_label'), 'required');
        if($identity_column!=='email')
        {
            $this->form_validation->set_rules('identity',$this->lang->line('create_user_validation_identity_label'),'required|is_unique['.$tables['users'].'.'.$identity_column.']');
            $this->form_validation->set_rules('email', $this->lang->line('create_user_validation_email_label'), 'required|valid_email');
        }
        else
        {
            $this->form_validation->set_rules('email', $this->lang->line('create_user_validation_email_label'), 'required|valid_email|is_unique[' . $tables['users'] . '.email]');
        }
        $this->form_validation->set_rules('phone', $this->lang->line('create_user_validation_phone_label'), 'trim');
        $this->form_validation->set_rules('company', $this->lang->line('create_user_validation_company_label'), 'trim');
        $this->form_validation->set_rules('password', $this->lang->line('create_user_validation_password_label'), 'required|min_length[' . $this->config->item('min_password_length', 'ion_auth') . ']|max_length[' . $this->config->item('max_password_length', 'ion_auth') . ']|matches[password_confirm]');
        $this->form_validation->set_rules('password_confirm', $this->lang->line('create_user_validation_password_confirm_label'), 'required');
        if ($this->form_validation->run() == true)
        {
            $email    = strtolower($this->input->post('email'));
            $identity = ($identity_column==='email') ? $email : $this->input->post('identity');
            $password = $this->input->post('password');
            $additional_data = array(
                'first_name' => $this->input->post('first_name'),
                'last_name'  => $this->input->post('last_name'),
                'company'    => $this->input->post('company'),
                'phone'      => $this->input->post('phone'),
            );
        }
        if ($this->form_validation->run() == true && $this->ion_auth->register($identity, $password, $email, $additional_data))
        {
            // check to see if we are creating the user
            // redirect them back to the admin page
            $this->session->set_flashdata('message', $this->ion_auth->messages());
            redirect(base_url('auth/users'), 'refresh');
        }
        else
        {
            // display the create user form
            // set the flash data error message if there is one
            $this->data['message'] = (validation_errors() ? validation_errors() : ($this->ion_auth->errors() ? $this->ion_auth->errors() : $this->session->flashdata('message')));
            $this->data['first_name'] = array(
                'name'  => 'first_name',
                'id'    => 'first_name',
                'type'  => 'text',
                'value' => $this->form_validation->set_value('first_name'),
                'class' => 'col-md-12'
            );
            $this->data['last_name'] = array(
                'name'  => 'last_name',
                'id'    => 'last_name',
                'type'  => 'text',
                'value' => $this->form_validation->set_value('last_name'),
                'class' => 'col-md-12'
            );
            $this->data['identity'] = array(
                'name'  => 'identity',
                'id'    => 'identity',
                'type'  => 'text',
                'value' => $this->form_validation->set_value('identity'),
                'class' => 'col-md-12'
            );
            $this->data['email'] = array(
                'name'  => 'email',
                'id'    => 'email',
                'type'  => 'text',
                'value' => $this->form_validation->set_value('email',''),
                'class' => 'col-md-12'
            );
            $this->data['company'] = array(
                'name'  => 'company',
                'id'    => 'company',
                'type'  => 'text',
                'value' => $this->form_validation->set_value('company','Minoka'),
                'class' => 'col-md-12'
            );
            $this->data['phone'] = array(
                'name'  => 'phone',
                'id'    => 'phone',
                'type'  => 'text',
                'value' => $this->form_validation->set_value('phone'),
                'class' => 'col-md-12'
            );
            $this->data['password'] = array(
                'name'  => 'password',
                'id'    => 'password',
                'type'  => 'password',
                'value' => $this->form_validation->set_value('password'),
                'class' => 'col-md-12'
            );
            $this->data['password_confirm'] = array(
                'name'  => 'password_confirm',
                'id'    => 'password_confirm',
                'type'  => 'password',
                'value' => $this->form_validation->set_value('password_confirm'),
                'class' => 'col-md-12'
            );
            $user = $this->ion_auth->user()->row();
            $this->data['username']  =   $user->username;
    
            $this->data['view']     =   'auth/create_user';
            $this->data['footer']         =   $this->home->page('Footer'); 
            $this->load->view('backend',$this->data);  
        }
    }
	// edit a user
function edit_user($id)
	{
		$this->data['title'] = $this->lang->line('edit_user_heading');
		if (!$this->ion_auth->logged_in() || (!$this->ion_auth->is_admin() && !($this->ion_auth->user()->row()->id == $id)))
		{
			redirect('auth', 'refresh'); 
		}
		           
        $user = $this->ion_auth->user($id)->row();
		$this->data['avatar']   =   profile_img($user->image);
        $groups=$this->ion_auth->groups()->result_array();
		$currentGroups = $this->ion_auth->get_users_groups($id)->result();
        $this->data['username']  =   $user->username;
        // validate form input
		$this->form_validation->set_rules('first_name', $this->lang->line('edit_user_validation_fname_label'), 'required');
		$this->form_validation->set_rules('last_name', $this->lang->line('edit_user_validation_lname_label'), 'required');
		if (isset($_POST) && !empty($_POST))
		{
			// do we have a valid request?
			if ($this->_valid_csrf_nonce() === FALSE || $id != $this->input->post('id'))
			{
				show_error($this->lang->line('error_csrf'));
			}
			// update the password if it was posted
			if ($this->input->post('password'))
			{
				$this->form_validation->set_rules('password', $this->lang->line('edit_user_validation_password_label'), 'required|min_length[' . $this->config->item('min_password_length', 'ion_auth') . ']|max_length[' . $this->config->item('max_password_length', 'ion_auth') . ']|matches[password_confirm]');
				$this->form_validation->set_rules('password_confirm', $this->lang->line('edit_user_validation_password_confirm_label'), 'required');
			}
			if ($this->form_validation->run() === TRUE)
			{
				$data = array(
					'first_name' => $this->input->post('first_name'),
					'last_name'  => $this->input->post('last_name'),
					'company'    => $this->input->post('company'),
					'phone'      => $this->input->post('phone'),
				);
				// update the password if it was posted
				if ($this->input->post('password'))
				{
					$data['password'] = $this->input->post('password');
				}
				// Only allow updating groups if user is admin
				if ($this->ion_auth->is_admin())
				{
					//Update the groups user belongs to
					$groupData = $this->input->post('groups');
					if (isset($groupData) && !empty($groupData)) {
						$this->ion_auth->remove_from_group('', $id);
						foreach ($groupData as $grp) {
							$this->ion_auth->add_to_group($grp, $id);
						}
					}
				}
			// check to see if we are updating the user
			   if($this->ion_auth->update($user->id, $data))
			    {
			    	// redirect them back to the admin page if admin, or to the base url if non admin
				    $this->session->set_flashdata('message', $this->ion_auth->messages() );
				    if ($this->ion_auth->is_admin())
					{
						redirect('auth/users', 'refresh');
					}
					else
					{
						redirect('/', 'refresh');
					}
			    }
			    else
			    {
			    	// redirect them back to the admin page if admin, or to the base url if non admin
				    $this->session->set_flashdata('message', $this->ion_auth->errors() );
				    if ($this->ion_auth->is_admin())
					{
						redirect('auth', 'refresh');
					}
					else
					{
						redirect('/', 'refresh');
					}
			    }
			}
		}
		// display the edit user form
		$this->data['csrf'] = $this->_get_csrf_nonce();
		// set the flash data error message if there is one
		$this->data['message'] = (validation_errors() ? validation_errors() : ($this->ion_auth->errors() ? $this->ion_auth->errors() : $this->session->flashdata('message')));
		// pass the user to the view
		$this->data['user'] = $user;
		$this->data['groups'] = $groups;
		$this->data['currentGroups'] = $currentGroups;
		$this->data['first_name'] = array(
			'name'  => 'first_name',
			'id'    => 'first_name',
			'type'  => 'text',
			'value' => $this->form_validation->set_value('first_name', $user->first_name),
                'class' => 'col-md-12'
		);
		$this->data['last_name'] = array(
			'name'  => 'last_name',
			'id'    => 'last_name',
			'type'  => 'text',
			'value' => $this->form_validation->set_value('last_name', $user->last_name),
                'class' => 'col-md-12'
		);
		$this->data['company'] = array(
			'name'  => 'company',
			'id'    => 'company',
			'type'  => 'text',
			'value' => $this->form_validation->set_value('company', $user->company),
                'class' => 'col-md-12'
		);
		$this->data['phone'] = array(
			'name'  => 'phone',
			'id'    => 'phone',
			'type'  => 'text',
			'value' => $this->form_validation->set_value('phone', $user->phone),
                'class' => 'col-md-12'
		);
		$this->data['password'] = array(
			'name' => 'password',
			'id'   => 'password',
			'type' => 'password',
                'class' => 'col-md-12'
		);
		$this->data['password_confirm'] = array(
			'name' => 'password_confirm',
			'id'   => 'password_confirm',
			'type' => 'password',
                'class' => 'col-md-12'
		);
        $user = $this->ion_auth->user()->row();
        $this->data['username']  =   $user->username;

        $this->data['view']    =   'auth/edit_user';
            $this->data['footer']     =   $this->home->page('Footer'); 
        $this->load->view('backend',$this->data); 
	}
	// create a new group
function create_group()
	{
		$this->data['title'] = $this->lang->line('create_group_title');
		if (!$this->ion_auth->logged_in() || !$this->ion_auth->is_admin())
		{
			redirect('auth', 'refresh');
		}
		
        // validate form input
		$this->form_validation->set_rules('group_name', $this->lang->line('create_group_validation_name_label'), 'required|alpha_dash');
		if ($this->form_validation->run() == TRUE)
		{
			$new_group_id = $this->ion_auth->create_group($this->input->post('group_name'), $this->input->post('description'));
			if($new_group_id)
			{
				// check to see if we are creating the group
				// redirect them back to the admin page
				$this->session->set_flashdata('message', $this->ion_auth->messages());
				redirect(base_url('auth/users'), 'refresh');
			}
		}
		else
		{
			// display the create group form
			// set the flash data error message if there is one
			$this->data['message'] = (validation_errors() ? validation_errors() : ($this->ion_auth->errors() ? $this->ion_auth->errors() : $this->session->flashdata('message')));
			$this->data['group_name'] = array(
				'name'  => 'group_name',
				'id'    => 'group_name',
				'type'  => 'text',
				'value' => $this->form_validation->set_value('group_name'),
			);
			$this->data['description'] = array(
				'name'  => 'description',
				'id'    => 'description',
				'type'  => 'text',
				'value' => $this->form_validation->set_value('description'),
			);
            $user = $this->ion_auth->user()->row();
            $this->data['username']  =   $user->first_name;
            $this->data['avatar']   =   profile_img($user->image);
            $this->data['view']    =   'auth/create_group';
            $this->data['footer']     =   $this->home->page('Footer'); 
            $this->load->view('backend',$this->data); 
		}
	}
	// edit a group
function edit_group($id)
	{
		// bail if no group id given
		if(!$id || empty($id))
		{
			redirect('auth', 'refresh');
		}
		$this->data['title'] = $this->lang->line('edit_group_title');
		if (!$this->ion_auth->logged_in() || !$this->ion_auth->is_admin())
		{
			redirect('auth', 'refresh');
		}
		$group = $this->ion_auth->group($id)->row();
		// validate form input
		$this->form_validation->set_rules('group_name', $this->lang->line('edit_group_validation_name_label'), 'required|alpha_dash');
		if (isset($_POST) && !empty($_POST))
		{
			if ($this->form_validation->run() === TRUE)
			{
				$group_update = $this->ion_auth->update_group($id, $_POST['group_name'], $_POST['group_description']);
				if($group_update)
				{
					$this->session->set_flashdata('message', $this->lang->line('edit_group_saved'));
				}
				else
				{
					$this->session->set_flashdata('message', $this->ion_auth->errors());
				}
				redirect(base_url('auth'), 'refresh');
			}
		}
		// set the flash data error message if there is one
		$this->data['message'] = (validation_errors() ? validation_errors() : ($this->ion_auth->errors() ? $this->ion_auth->errors() : $this->session->flashdata('message')));
		// pass the user to the view
		$this->data['group'] = $group;
		$readonly = $this->config->item('admin_group', 'ion_auth') === $group->name ? 'readonly' : '';
		$this->data['group_name'] = array(
			'name'    => 'group_name',
			'id'      => 'group_name',
			'type'    => 'text',
			'value'   => $this->form_validation->set_value('group_name', $group->name),
			$readonly => $readonly,
		);
		$this->data['group_description'] = array(
			'name'  => 'group_description',
			'id'    => 'group_description',
			'type'  => 'text',
			'value' => $this->form_validation->set_value('group_description', $group->description),
		);
            $user = $this->ion_auth->user()->row();
            $this->data['username']  =   $user->username;
    
            $this->data['view']    =   'auth/edit_group';
            $this->data['footer']     =   $this->home->page('Footer'); 
            $this->load->view('backend',$this->data); 
	}
function _get_csrf_nonce()
	{
		$this->load->helper('string');
		$key   = random_string('alnum', 8);
		$value = random_string('alnum', 20);
		$this->session->set_flashdata('csrfkey', $key);
		$this->session->set_flashdata('csrfvalue', $value);
		return array($key => $value);
	}
function _valid_csrf_nonce()
	{
		if ($this->input->post($this->session->flashdata('csrfkey')) !== FALSE &&
			$this->input->post($this->session->flashdata('csrfkey')) == $this->session->flashdata('csrfvalue'))
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
function _render_page($view, $data=null, $returnhtml=false)//I think this makes more sense
	{
		$this->viewdata = (empty($data)) ? $this->data: $data;
		$view_html = $this->load->view($view, $this->viewdata, $returnhtml);
                if ($returnhtml) {return $view_html;}//This will return html on 3rd argument being true
	}
function menu($activ=FALSE)
    {
        return array(   '0'=>array( 'text'=>$this->lang->line('users'),'link'=>base_url('auth/users'),'class'=>'btn btn-default btn-menu'),
                        //'1'=>array( 'text'=>$this->lang->line('groups'),'link'=>base_url('auth/create_group'),'class'=>'btn btn-default btn-menu')
                        );
    }
public function usernameExist($username)
    {
        $this->load->model('auth_users_m','user_model');
        $user_id    =   $this->session->userdata('user_id');
        $exist = $this->user_model->usernameExists($username,$user_id);
        if ($exist)
            {
                $this->form_validation->set_message('usernameExist', $this->lang->line('username_exists'));
                return false;
            }
        else{   return true;}
    }
}