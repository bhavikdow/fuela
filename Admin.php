<?php 
defined("BASEPATH") or exit ("No direct script access allowed");
require APPPATH.'third_party/cloudinary/autoload.php';

use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;
/**
 * 
 */
class Admin extends CI_Controller{
	
	public function __construct(){
		parent::__construct();
		$this->load->model(['admin/admin_model', 'admin/hobby_model']);
	}

	public function loadview($loadview, $data=null){
		$this->load->view('admin/common/header',$data);
		$this->load->view('admin/'.$loadview);
		$this->load->view('admin/common/footer');
	}

	public function dashboard_loadview($loadview,$data=NULL){
		$admin_id=$this->session->userdata('admin_id');
        $admin['unseen_notification_count'] = $this->admin_model->getAdminUnseenNotification();
        $admin['notification'] = $this->admin_model->getAdminNotification();
        $i = 0;
        foreach ($admin['notification'] as $key => $value) {
            $user_image = $this->admin_model->getUserImage($value['reported_user_id']);
            $admin['notification'][$i]['time'] = convertToHoursMinsSec(date('Y-m-d H:i:s', strtotime($value['created_at'])));
            if(!empty($user_image['image_url'])){
                $admin['notification'][$i]['image_url'] = $user_image['image_url'];
            }else{
                $admin['notification'][$i]['image_url'] = base_url('assets/admin/no_image_avail.png');
            }
            $i++;
        }
		$data['admin_detail']=$this->admin_model->getAdminDetail($admin_id);
		$this->load->view('admin/common/header',$data);
		$this->load->view('admin/common/sidebar',$data);
		$this->load->view('admin/'.$loadview);
		$this->load->view('admin/common/footer');
    }

    private  function is_login(){
		$admin_id=$this->session->userdata('admin_id');
		if(empty($admin_id)){
			redirect('boo');
		}else{
			return $admin_id;
		}
    }

    //----------------------------- Upload single file-----------------------------

	public function doUploadImage($file_name) {
        Configuration::instance([
            'cloud' => [
                "cloud_name" => $this->config->item('cloudinary_cloud'), 
                "api_key" => $this->config->item('cloudinary_api_key'), 
                "api_secret" => $this->config->item('cloudinary_api_sercret')],
                'url' => [
                    'secure' => true
                ]
            ]);
        $profile_image = (new UploadApi())->upload($file_name, [
            'resource_type' => 'image',
            'public_id' => 'nassibo/'.rand(1111, 9999),
            'chunk_size' => 6000000,
            'eager_async' => true]
        );
        $img = json_encode($profile_image);
        $image = json_decode($img);
        $image_url = $image->secure_url;
        return $image_url;
    }

    //----------------------------- Upload multiple files-----------------------------

    public function upload_files($path,$file_name){
        $this->output->set_content_type('application/json');
        $files = $_FILES[$file_name];
        $config = array(
            'upload_path'   => $path,
            'allowed_types' => 'jpeg|jpg|gif|png|pdf',
            'overwrite'     => 1,                       
        );
        $this->load->library('upload', $config);
        $images = array();
        $i=0;
        foreach ($files['name'] as $key => $image) {
            $_FILES['images[]']['name']= $files['name'][$key];
            $_FILES['images[]']['type']= $files['type'][$key];
            $_FILES['images[]']['tmp_name']= $files['tmp_name'][$key];
            $_FILES['images[]']['error']= $files['error'][$key];
            $_FILES['images[]']['size']= $files['size'][$key];

            $title = rand('1111','9999');
            $image = explode('.',$image);
            $count = count($image);
            $extension = $image[$count-1];
            $fileName = $title .'.'. $extension;
            $images[$i] = $fileName;
            $config['file_name'] = $fileName;
            $this->upload->initialize($config);

            if ($this->upload->do_upload('images[]')) {
                $this->upload->data();
            } else {
                return $this->upload->display_errors();
            }
            $i++;
        }
        return $images;
    }

	public function index(){
		if(!empty($this->session->userdata('admin_id'))){
			redirect('admin/dashboard');
		}
		$data['title'] = 'Admin Login';
		$data['admin_detail']=$this->admin_model->getAdminDetail(1);
		$this->load->view('admin/login', $data);
	}

	public function check_login(){
		$this->output->set_content_type('application/json');
		$this->form_validation->set_rules('email', 'Email', 'required|valid_email');
        $this->form_validation->set_rules('password', 'Password', 'required');
        if ($this->form_validation->run() === FALSE) {
            $this->output->set_output(json_encode(['result' => 0, 'errors' => $this->form_validation->error_array()]));
            return FALSE;
        }
        $result = $this->admin_model->checkLogin();
        if ($result) {
            $this->session->set_userdata('admin_id', $result['id']);
            $this->output->set_output(json_encode(['result' => 3, 'url' => base_url('admin/dashboard'), 'msg' => 'Loading!! Please Wait...']));
            return FALSE;
        } else {
            $this->output->set_output(json_encode(['result' => -3, 'msg' => 'Invalid username or password']));
            return FALSE;
		}
	}

    public function forgot_password(){
        $this->output->set_content_type('application/json');
        $email = $this->input->post('email');
        $admin_detail = $this->admin_model->get_admin_by_email($email);
        if(!empty($admin_detail)){
            $this->send_password_reset_mail($admin_detail);
            $this->admin_model->forgetPasswordLinkValidity($admin_detail['id']);
            $this->output->set_output(json_encode(['result' => 3, 'msg'=>'Reset Password Link has been sent to your E-mail Id.','url'=> base_url('boo')]));
            return FALSE;
        }else{
            $this->output->set_output(json_encode(['result' => -3, 'msg'=>'Please Enter Valid Email Id.']));
            return FALSE;
        }
    }
    
    public function send_password_reset_mail($admin_detail){
        $encrypted_id = encryptId($admin_detail['id']);
        $htmlContent = "<h3>Dear " . $admin_detail['name'] . ",</h3>";
        $htmlContent .= "<div style='padding-top:8px;'>Please click the following link to reset your password.</div>";
        $htmlContent .= "<a href='" . base_url('admin/reset-password/' . $encrypted_id) . "'> Click Here!!</a>";
        $from = "admin@negotium.com";
        $to = $admin_detail['email'];
        $subject = "[nassibo] Forgot Password";
        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
        $headers .= 'From: ' . $from . "\r\n";
        @mail($to, $subject, $htmlContent, $headers);
        return FALSE;
    }
    
    public function reset_password($id){
        $admin_id = decryptId($id);
        $data['admin_detail'] = $this->admin_model->getAdminDetail($admin_id);
        $data['title'] = "Reset Password";        
        $data['admin_id'] = $admin_id;
        $forget_password = $this->admin_model->getLinkValidity($admin_id);
        if($forget_password['status'] == 1){
            $data['forget_password'] = 'expired';
        }else{
            $data['forget_password'] = 'valid';
        }
        $this->admin_model->linkValidity($admin_id);
        $this->load->view('admin/reset_password',$data);
        
    }
    
    public function do_reset_password(){
        $this->output->set_content_type('application/json');
        $this->form_validation->set_rules('new_password', 'New Password', 'required');
        $this->form_validation->set_rules('confirm_password', 'Confirm Password', 'required|matches[new_password]');
        if ($this->form_validation->run() === FALSE) {
            $this->output->set_output(json_encode(['result' => 0, 'errors' => $this->form_validation->error_array()]));
            return FALSE;
        }
        $encrypted_id = $this->input->post('admin_id');
        $result = $this->admin_model->do_fogot_password($encrypted_id);
        if (!empty($result)) {
            $this->output->set_output(json_encode(['result' => 3, 'url' => base_url('boo'), 'msg' => 'Pasword Reset Successfully']));
            return FALSE;
        } else {
            $this->output->set_output(json_encode(['result' => -3, 'msg' => 'New Password Cannot Be Same As Old Password.']));
            return FALSE;
        }
    }

	public function logout(){
        $this->output->set_content_type('application/json');
        $admin_id=$this->is_login();
        $this->session->unset_userdata('admin_id');
        $this->output->set_output(json_encode(['result' => 1, 'url' => base_url('boo')]));
        return FALSE;
	}

	public function dashboard(){
		$this->is_login();
		$data['title'] = 'Admin Dashboard';    
        $data['user_count'] = count($this->admin_model->getUsersCount());
        $data['user_count_by_malegender'] = count($this->admin_model->usersCountByGender('man'));
        $data['user_count_by_femalegender'] = count($this->admin_model->usersCountByGender('woman'));
        $data['reported_user_count'] = count($this->admin_model->reportedUsersCount());

        $data['religion'] = $this->hobby_model->getAllReligions();
        foreach($data['religion'] as $key => $value){
            $data['religion_users'][$value['name']] = $this->admin_model->getReligiousUsers(strtolower($value['name']));
        }

        $data['belief_specifications'] = $this->hobby_model->getAllBeliefSpecifications();
        foreach($data['belief_specifications'] as $key => $value){
            $data['belief_specifications_users'][$value['name']] = $this->admin_model->getBeliefSpecificationUsers($value['id']);
        }

        $data['habits'] = $this->hobby_model->getAllHabits();
        foreach($data['habits'] as $key => $value){
            $data['habits_users'][$value['name']] = $this->admin_model->getHabitsUsers($value['id']);
        }
		$this->dashboard_loadview('dashboard', $data);
	}

    public function profile(){
        $admin_id=$this->is_login();
        $data['title']='Profile';
        $data['admin_detail']=$this->admin_model->getAdminDetail($admin_id);
        $this->dashboard_loadview('profile',$data);
    }

    public function updateProfile() {
        $admin_id=$this->is_login();
        $this->output->set_content_type('application/json');
        $this->form_validation->set_rules('name', 'First Name', 'required');
        $this->form_validation->set_rules('support_email', 'Support E-mail', 'required');
        $this->form_validation->set_rules('address', 'Address', 'required');
        if ($this->form_validation->run() === FALSE) {
            $this->output->set_output(json_encode(['result' => 0, 'errors' => $this->form_validation->error_array()]));
            return FALSE;
        }
        
        if (!empty($_FILES['image_url']['name'])) {
            $image_url = $this->doUploadImage($_FILES['image_url']['tmp_name']);
        } else {
            $admin = $this->admin_model->getAdminDetail($admin_id);
            $image_url = $admin['image_url'];
        }
        if (!empty($_FILES['profile_image']['name'])) {
            $profile_image = $this->doUploadImage($_FILES['profile_image']['tmp_name']);
        } else {
            $admin = $this->admin_model->getAdminDetail($admin_id);
            $profile_image = $admin['profile_image'];
        }
        if (!empty($_FILES['favicon_icon']['name'])) {
            $favicon_icon = $this->doUploadImage($_FILES['favicon_icon']['tmp_name']);
        } else {
            $admin = $this->admin_model->getAdminDetail($admin_id);
            $favicon_icon = $admin['favicon_icon'];
        }
        $result=$this->admin_model->updateProfile($admin_id,$image_url,$profile_image,$favicon_icon);
        
        if ($result) {
            $this->output->set_output(json_encode(['result' => 1, 'msg' => 'Profile Updated Succesfully','url' => base_url('admin/profile')]));
            return FALSE;
        } else {
            $this->output->set_output(json_encode(['result' => -1, 'msg' => 'Something Went Wrong']));
            return FALSE;
        }
    }

    public function changePassword(){
        $admin_id=$this->is_login();
        $this->output->set_content_type('application/json');
        $this->form_validation->set_rules('old_password', 'Old Password', 'required');
        if ($this->form_validation->run() === FALSE) {
            $this->output->set_output(json_encode(['result' => 0, 'errors' => $this->form_validation->error_array()]));
            return FALSE;
        }
        $result = $this->admin_model->do_check_oldpassword($admin_id);
        if (!empty($result)) {
            $this->form_validation->set_rules('new_password', 'New Password', 'required');
            $this->form_validation->set_rules('confirm_new_password', 'Confirm Password', 'required');
            if ($this->form_validation->run() === FALSE) {
                $this->output->set_output(json_encode(['result' => 0, 'errors' => $this->form_validation->error_array()]));
                return FALSE;
            }

            if($this->input->post('new_password')==$this->input->post('confirm_new_password')){
                $changed = $this->admin_model->do_reset_passowrd($admin_id);
                if ($changed) {
                    $this->output->set_output(json_encode(['result' => 1, 'url' => base_url('boo'), 'msg' => 'Password successfully changed.']));
                    return FALSE;
                }else{
                    $this->output->set_output(json_encode(['result' => -1, 'msg' => 'Old Password and New Password should not be same.']));
                    return FALSE;
                }
            }else{
                $this->output->set_output(json_encode(['result' => -1, 'msg' => 'New password and Confirm Password should be same.']));
                return FALSE;
            }
        } else {
            $this->output->set_output(json_encode(['result' => -1, 'msg' => 'Old password did not matched current password.']));
            return FALSE;
        }
    }

    public function social(){
        $admin_id=$this->is_login();
        $data['title']='Social';
        $data['social_link']=$this->config->item('social_link');
        $data['social_data']=$this->admin_model->get_social_link();
        $this->dashboard_loadview('social',$data);
    }

    public function add_social_link(){
        $admin_id=$this->is_login();
        $this->output->set_content_type('application/json');
        $result=$this->admin_model->add_social_link();
        if ($result) {
            $this->output->set_output(json_encode(['result' => 1, 'url' => base_url('admin/social'), 'msg' => 'Social link updated successfully.']));
            return FALSE;
        }else{
            $this->output->set_output(json_encode(['result' => 0, 'msg' => 'OOPs Something went wrong']));
            return FALSE;
        }
    }

    public function site_setting($key){
        $admin_id = $this->is_login();
        if($key == 'terms'){
            $data['title'] = 'Privacy Policy and Terms of Service';
        }elseif($key == 'privacy'){
            $data['title'] = 'Privacy Policies';
        }elseif($key == 'safety'){
            $data['title'] = 'Safety Tips';
        }else{
            $data['title'] = 'Our Guidelines';
        }
        $data['basic_datatable'] = '1';
        $data['type'] = $key;
        $data['site_setting'] = $this->admin_model->site_setting($key);
        $this->dashboard_loadview('site_setting',$data);
    }

    public function update_site_setting(){
        $this->output->set_content_type('application/json');
        $admin_id=$this->is_login();
        $this->form_validation->set_rules('description', 'Description', 'required');
        if ($this->form_validation->run() === FALSE) {
            $this->output->set_output(json_encode(['result' => 0, 'errors' => $this->form_validation->error_array()]));
            return FALSE;
        }
        $result=$this->admin_model->update_site_setting();
        $key = $this->input->post('type');
        if($key == 'terms'){
            $url = base_url('admin/terms-and-condition');
            $title = 'Privacy Policy and Terms of Service';
        }elseif($key == 'privacy'){
            $url = base_url('admin/privacy-policy');
            $title = 'Privacy Policies';
        }elseif($key == 'safety'){
            $url = base_url('admin/safety-tips');
            $data['title'] = 'Safety Tips';
        }else{
            $url = base_url('admin/guidelines');
            $title = 'Our Guidelines';
        }
        if ($result) {
            $this->output->set_output(json_encode(['result' => 1, 'url' => $url, 'msg' => $title.' Updated successfully.']));
            return FALSE;
        }else{
            $this->output->set_output(json_encode(['result' => -1, 'msg' => 'No changes found.']));
            return FALSE;
        }
    }

    function change_status($id,$status,$table,$unique_id,$status_variable){
        $this->output->set_content_type('application/json');
        change_status($id,$status,$table,$unique_id,$status_variable);
        if($status == 'Deleted'){
            $msg = ucwords(str_replace('_', ' ', $table)).' deleted successfully.';
        }else{
            $msg = ucwords(str_replace('_', ' ', $table)).' status change to '.strtolower($status).' successfully.';
        }
        $this->output->set_output(json_encode(['result' => 1,'msg'=> $msg]));
        return FALSE;
    }

    public function faq(){
        $admin_id = $this->is_login();
        $data['title'] = "Faq's";
        $data['basic_datatable']='1';
        $data['faq']=$this->admin_model->get_all_faqs();
        $this->dashboard_loadview('faq/faq',$data);
    }

    public function open_faq_form($id=NULL){
        $this->output->set_content_type('application/json');
        $admin_id=$this->is_login();
        if(empty($id)){
            $data['data'] = NULL;
            $model_wrapper = $this->load->view('admin/faq/faq-form',$data,true);
        }else{
            $data['faq_detail'] = $this->admin_model->get_faq_by_id($id);
            $model_wrapper = $this->load->view('admin/faq/faq-form',$data,true);
        }
        $this->output->set_output(json_encode(['result' => 1, 'model_wrapper' => $model_wrapper]));
        return false;
    }

    public function add_faq(){
        $this->output->set_content_type('application/json');
        $admin_id=$this->is_login();
        $this->form_validation->set_rules('question', 'Question', 'required');
        $this->form_validation->set_rules('answer', 'Answer', 'required');
        if ($this->form_validation->run() === FALSE) {
            $this->output->set_output(json_encode(['result' => 0, 'errors' => $this->form_validation->error_array()]));
            return FALSE;
        }
        $result=$this->admin_model->add_faq();
        if ($result) {
            $this->output->set_output(json_encode(['result' => 1, 'url' => base_url('admin/faq'), 'msg' => 'Faq Added successfully ']));
            return FALSE;
        }else{
            $this->output->set_output(json_encode(['result' => -1, 'msg' => 'OOPs Something went wrong']));
            return FALSE;
        }
    }

    public function update_faq($id){
        $this->output->set_content_type('application/json');
        $admin_id=$this->is_login();
        $this->form_validation->set_rules('question', 'Question', 'required');
        $this->form_validation->set_rules('answer', 'Answer', 'required');
        if ($this->form_validation->run() === FALSE) {
            $this->output->set_output(json_encode(['result' => 0, 'errors' => $this->form_validation->error_array()]));
            return FALSE;
        }
        $result = $this->admin_model->update_faq($id);
        if ($result) {
            $this->output->set_output(json_encode(['result' => 1, 'url' => base_url('admin/faq'), 'msg' => 'Faq Updated successfully.']));
            return FALSE;
        }else{
            $this->output->set_output(json_encode(['result' => -1, 'msg' => 'OOPs Something went wrong']));
            return FALSE;
        }
    }

    public function users_feedback(){
        $admin_id = $this->is_login();
        $data['title'] = 'Users Feedback';
        $data['basic_datatable'] = '1';
        $data['users_feedback'] = $this->admin_model->getAllUsersFeedback();
        $this->dashboard_loadview('users/user-feedback',$data);
    }

    public function users($gender=NULL){
        $admin_id = $this->is_login();
        if(!in_array($gender,['man','woman'])){
            $gender=null;
        }
        $data['title'] = 'Users';
        $data['basic_datatable'] = '1';
        $data['users'] = $this->admin_model->getAllUsers($gender);
        $this->dashboard_loadview('users/users',$data);
    }

    public function users_religion($religion=NULL){
        $admin_id = $this->is_login();
        $data['title'] = 'Users';
        $data['basic_datatable'] = '1';
        $data['users'] = $this->admin_model->getAllReligiousUsers($religion);
        $this->dashboard_loadview('users/users',$data);
    }

    public function users_belief($belief=NULL){
        $admin_id = $this->is_login();
        $belief_id = decryptId($belief);
        $data['title'] = 'Users';
        $data['basic_datatable'] = '1';
        $data['users'] = $this->admin_model->getAllReligiousBeliefUsers($belief_id);
        $this->dashboard_loadview('users/users',$data);
    }

    public function users_habits($habits=NULL){
        $admin_id = $this->is_login();
        $habits_id = decryptId($habits);
        $data['title'] = 'Users';
        $data['basic_datatable'] = '1';
        $data['users'] = $this->admin_model->getAllhabitsUsers($habits_id);
        $this->dashboard_loadview('users/users',$data);
    }

    public function notification(){
        $this->output->set_content_type('application/json');
        $admin_id=$this->is_login();
        $data['user_id'] = $this->input->post('user_id');
        $data['data'] = NULL;
        $model_wrapper = $this->load->view('admin/users/notification',$data,true);
        $this->output->set_output(json_encode(['result' => 1, 'model_wrapper' => $model_wrapper]));
        return false;
    }

    public function send_notification(){
        $this->output->set_content_type('application/json');
        $admin_id=$this->is_login();
        $this->form_validation->set_rules('message', 'message', 'required');
        if ($this->form_validation->run() === FALSE) {
            $this->output->set_output(json_encode(['result' => 0, 'errors' => $this->form_validation->error_array()]));
            return FALSE;
        }
        
        $user_id = $this->input->post('user_id');
        $message = $this->input->post('message');
        $subject = $this->input->post('subject');
        foreach($user_id as $id){
            $user_detail = $this->admin_model->getUserDetail($id);
            $this->send_notification_mail($message,$user_detail['name'],$user_detail['email'],$subject);
            $notification = $this->admin_model->save_notification($id);
        }
        if($notification){
            $this->output->set_output(json_encode(['result' => 1, 'msg' => 'Notification Sent Successfully.','url'=> base_url('admin/users')]));
            return false;
        }else{
            $this->output->set_output(json_encode(['result' => -1, 'msg' => 'Something went wrong.']));
            return false;
        }
    }

    public function send_notification_mail($message, $name, $email,$subject){
        $htmlContent = "<h3>Dear " . $name . ",</h3>";
        $htmlContent .= "<div style='padding-top:8px;'>".$message."</div>";
        $admin_id=$this->session->userdata('admin_id');
        $admin_detail=$this->admin_model->getAdminSupportEmail($admin_id);
        if(!empty($admin_detail)){
            $from = $admin_detail['support_email'];
        }else{
            $from = 'support@nassibo.com';
        }
        $to = $email;
        $subject = "[nassibo] ".$subject;
        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
        $headers .= 'From: ' . $from . "\r\n";
        @mail($to, $subject, $htmlContent, $headers);
        return true;
    }

    public function users_detail($id){
        $admin_id = $this->is_login();
        $data['title'] = 'User Notification';
        $data['basic_datatable']='1';
        $data['user_detail'] = $this->admin_model->getUserDetail($id);
        $data['user_notification'] = $this->admin_model->getUserNotification($id);
        $this->dashboard_loadview('users/user-detail',$data);
    }

    public function reported_users(){
        $admin_id = $this->is_login();
        $data['title'] = 'Reported Users List';
        $data['basic_datatable'] = '1';
        $data['reported_users'] = $this->admin_model->getAllReportedUsers();
        $i = 0;
        foreach ($data['reported_users'] as $key => $value) {
            $reported_user = $this->admin_model->getReportedUserName($value['reported_user_id']);
            $data['reported_users'][$i]['reported_user_first_name'] = $reported_user['first_name'];
            $data['reported_users'][$i]['reported_user_last_name'] = $reported_user['last_name'];
            $data['reported_users'][$i]['reported_user_image_url'] = $reported_user['image_url'];
            $i++;
        }
        $this->dashboard_loadview('users/reported_users',$data);
    }

    public function deleted_users(){
        $admin_id = $this->is_login();
        $data['title'] = 'Deleted Users List';
        $data['basic_datatable'] = '1';
        $data['deleted_users'] = $this->admin_model->getAllDeletedUsers();
        $this->dashboard_loadview('users/deleted_users',$data);
    }

    public function view_reported_users_list($reported_user_id){
        $admin_id = $this->is_login();
        $id = decryptId($reported_user_id);
        $data['title'] = 'View Reported Users List';
        $data['basic_datatable'] = '1';
        $data['reported_users_list'] = $this->admin_model->getAllReportedUsersList($id);

        $i = 0;
        foreach ($data['reported_users_list'] as $key => $value) {
            $reported_user = $this->admin_model->getReportedUserName($value['reported_user_id']);
            $data['reported_users_list'][$i]['reported_user_first_name'] = $reported_user['first_name'];
            $data['reported_users_list'][$i]['reported_user_last_name'] = $reported_user['last_name'];
            $data['reported_users_list'][$i]['reported_user_image_url'] = $reported_user['image_url'];
            $data['reported_users_list'][$i]['blocked'] = $reported_user['blocked'];
            $i++;
        }
        $this->dashboard_loadview('users/view_reported_users_list',$data);
    }

    public function blockUser($block_status, $id){
        $this->output->set_content_type('application/json');
        if($block_status == 'no'){
            $result = $this->admin_model->blockUser($id);
            if($result){
                $this->output->set_output(json_encode(['result' => 1, 'msg'=> 'User Blocked.', 'url' => base_url('admin/reported_users')]));
                return FALSE;
            }
        }else{
            $result = $this->admin_model->unblockUser($id);
            if($result){
                $this->output->set_output(json_encode(['result' => 1, 'msg'=> 'User Unblocked.', 'url' => base_url('admin/reported_users')]));
                return FALSE;
            }
        }
    }

    public function exportToDoc(){
        header("Content-type: application/vnd.ms-word");  
        header("Content-Disposition: attachment;Filename=".rand().".doc");  
        header("Pragma: no-cache");  
        header("Expires: 0");  
        echo 'Hlelll';
    }
}
?>