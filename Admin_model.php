<?php 
defined("BASEPATH") or exit ("No direct script access allowed");

/**
 * 
 */
class Admin_model extends CI_Model{
	
	public function __construct(){
		parent::__construct();
	}

	public function getAdminDetail($admin_id) {
        $query = $this->db->get_where('admin', ['id' => $admin_id]);
        return $query->row_array();
    }

    public function getAdminSupportEmail($admin_id) {
        $this->db->select('support_email');
        $query = $this->db->get_where('admin', ['id' => $admin_id]);
        return $query->row_array();
    }

    public function checkLogin() {
        $data = array(
            'email' => $this->security->xss_clean($this->input->post('email')),
            'password' => $this->security->xss_clean(hash('sha256', $this->input->post('password')))
        );
        $query = $this->db->get_where('admin',$data);
        return $query->row_array();
    }

    public function updateProfile($admin_id, $image_url,$profile_image,$favicon_icon) {
        $data = array(
            'name'          => $this->security->xss_clean($this->input->post('name')),
            'support_email' => $this->security->xss_clean($this->input->post('support_email')),
            'address'       => $this->security->xss_clean($this->input->post('address')),
            'image_url'     => $image_url,
            'profile_image' => $profile_image,
            'favicon_icon'  => $favicon_icon,
            'mobile'        => $this->security->xss_clean($this->input->post('mobile')),
        );
        $this->db->update('admin', $data, ['id' => $admin_id]);
        return true;
    }

    public function do_check_oldpassword($admin_id) {
        $oldpassword = $this->security->xss_clean($this->input->post('old_password'));
        $query = $this->db->get_where('admin', ['id' => $admin_id, 'password' => hash('sha256', $oldpassword)]);
        return $query->row_array();
    }

    public function do_reset_passowrd($admin_id) {
        $newpassword = $this->security->xss_clean($this->input->post('new_password'));
        $this->db->update('admin', ['password' => hash('sha256', $newpassword)], ['id' => $admin_id]);
        return $this->db->affected_rows();
    }

    public function get_admin_by_email($email) {
        $query = $this->db->get_where('admin', ['email' => $email]);
        return $query->row_array();
    }

    public function forgetPasswordLinkValidity($admin_id) {
        $data = array(
            'admin_id'  => $admin_id,
            'status'   => '0',
        );
        $this->db->insert('forgot_password',$data);
        $id = $this->db->insert_id();
        $sel = $this->db->get_where('forgot_password', ['forgot_password_id ' => $id]);
        return $sel->row_array();
    }

    public function linkValidity($admin_id) {
        $this->db->where(['admin_id' => $admin_id]);
        $this->db->update('forgot_password', ['status' => '1']);
        return $this->db->affected_rows();
    }
    
    public function getLinkValidity($admin_id){        
        $this->db->select('*');
        $this->db->from('forgot_password');
        $this->db->where('admin_id',$admin_id);
        $this->db->order_by('forgot_password_id','Desc');
        $sel = $this->db->get();
        return $sel->row_array();
    }

    public function do_fogot_password($id) {
        $newpassword = $this->security->xss_clean(hash('sha256', $this->input->post('new_password')));
        $this->db->update('admin', ['password' => $newpassword], ['id' => $id]);
        return $this->db->affected_rows();
    }

    public function get_social_link(){
        $this->db->select('*');
        $this->db->from('social');
        $this->db->where(['status'=>'1']);
        $this->db->order_by('id','DESC');
        $query=$this->db->get();
        return $query->result_array();
    }

    public function add_social_link(){
        $social_name=$this->input->post('social_name');
        $social_link=$this->input->post('social_link');
        $i=0;
        foreach($social_name as $value){
            $data=array(
                'social_name'=>$value,
                'social_link'=>$social_link[$i],
            );
            $this->db->select('*');
            $this->db->from('social');
            $this->db->where(['social_name'=>$value]);
            $query=$this->db->get();
                if($query->num_rows() >0){
                    $this->db->update('social',$data,['social_name'=>$value]);
                }else{
                    $this->db->insert('social',$data);
                }
            $i++;
        }
        return true;
    }

    public function site_setting($key){
        $this->db->select('*');
        $this->db->from('setting');
        $this->db->where('type',$key);
        $query=$this->db->get();
        return $query->row_array();
    }

    public function update_site_setting(){
        $data=array(
            'description'       =>$this->input->post('description'),
        );
        $this->db->update('setting', $data, ['type' => $this->input->post('type')]);
        return $this->db->affected_rows();
    }

    public function get_all_faqs(){
        $this->db->order_by('id', 'desc');
        $result = $this->db->get_where('faq', ['status !=' => 'Deleted']);
        return $result->result_array();
    }

    public function get_faq_by_id($faq_id){
        $result = $this->db->get_where('faq', ['id' => $faq_id]);
        return $result->row_array();
    }

    public function add_faq(){
        $data=array(
            'question'      =>$this->input->post('question'),
            'answer'        =>$this->input->post('answer'),
            'created_at'    =>date('Y-m-d H:i:s'),
        );
        $this->db->insert('faq',$data);
        $id=$this->db->insert_id();
        $query = $this->db->get_where('faq', ['id' =>$id ]);
        return $query->row_array();
    }

    public function update_faq($id){
        $data=array(
            'question'      =>$this->input->post('question'),
            'answer'        =>$this->input->post('answer'),
            'updated_at'    =>date('Y-m-d H:i:s'),
        );
        $this->db->update('faq', $data, ['id' => $id]);
        return true;
    }

    public function getAllUsers($gender,$source=NULL){
        $this->db->select('*');
        $this->db->from('users');
        $this->db->order_by('id','DESC');
        $this->db->where('step','completed');
        $this->db->where('status !=','Deleted');
        if(!empty($source)){
            $this->db->where('source',$source);
        }
        if(!empty($gender)){
            $this->db->where('gender',$gender);
        }
        $query=$this->db->get();
        return $query->result_array();
    }

    public function getAllReligiousUsers($religion){
        $this->db->select('*');
        $this->db->from('users');
        $this->db->order_by('id','DESC');
        $this->db->where('status !=','Deleted');
        if(!empty($religion)){
            $this->db->where('religion',$religion);
        }
        $query=$this->db->get();
        return $query->result_array();
    }

    public function getAllReligiousBeliefUsers($belief){
        $this->db->select('*');
        $this->db->from('users');
        $this->db->order_by('id','DESC');
        $this->db->where('status !=','Deleted');
        if(!empty($belief)){
            $this->db->where('specify_religion',$belief);
        }
        $query=$this->db->get();
        return $query->result_array();
    }

    public function getAllhabitsUsers($habits){
        $this->db->select('*');
        $this->db->from('users');
        $this->db->join('user_habits', 'users.id = user_habits.user_id');
        $this->db->order_by('users.id','DESC');
        $this->db->where('users.status !=','Deleted');
        if(!empty($habits)){
            $this->db->where('user_habits.habits',$habits);
        }
        $query=$this->db->get();
        return $query->result_array();
    }

    public function getUserDetail($id) {
        $query = $this->db->get_where('users', ['id' => $id]);
        return $query->row_array();
    }

    public function save_notification($user_id){
        $data = array(
            'user_id'       => $user_id,
            'message'       => $this->input->post('message'),
            'subject'       => $this->input->post('subject'),
            'status'        => 'Active',
            'created_at'    => date('Y-m-d H:i:s'),
        );
        $this->db->insert('user_notification',$data);
        $id = $this->db->insert_id();
        return $id;
    }

    public function getUserNotification($id){
        $this->db->select('user_notification.*');
        $this->db->from('user_notification');
        $this->db->where('user_id',$id);
        $this->db->order_by('id','DESC');
        $query=$this->db->get();
        return $query->result_array();
    }

    public function getAllUsersFeedback(){
        $this->db->select('contact_us.*, users.first_name, users.last_name, users.image_url');
        $this->db->from('contact_us');
        $this->db->join('users', 'users.id = contact_us.user_id');
        $this->db->order_by('id', 'desc');
        $result = $this->db->get();
        return $result->result_array();
    }

    public function getAllReportedUsers(){
        $this->db->select('report_user.id, report_user.user_id, report_user.reported_user_id, report_user.report_reason, report_user.description, report_options.report_options, users.first_name, users.last_name, users.image_url');
        $this->db->from('report_user');
        $this->db->join('users', 'users.id = report_user.user_id','left');
        $this->db->join('report_options', 'report_options.id = report_user.report_reason','left');
        //$this->db->group_by('report_user.reported_user_id');
        $this->db->order_by('report_user.id', 'desc');
        $result = $this->db->get();
        return $result->result_array();
    }

    public function getAllDeletedUsers(){
        $this->db->select('delete_reason.id, delete_reason.user_id, delete_reason.delete_reason, users.first_name, users.last_name, users.email, users.country_code, users.phone, users.image_url');
        $this->db->from('delete_reason');
        $this->db->join('users', 'users.id = delete_reason.user_id');
        $this->db->order_by('delete_reason.id', 'desc');
        $result = $this->db->get();
        return $result->result_array();
    }

    public function getAllReportedUsersList($id){
        $this->db->select('report_user.id, report_user.user_id, report_user.reported_user_id, report_user.report_reason, report_user.description, report_options.report_options, users.first_name, users.last_name, users.image_url');
        $this->db->from('report_user');
        $this->db->join('users', 'users.id = report_user.user_id');
        $this->db->join('report_options', 'report_options.id = report_user.report_reason');
        $this->db->where('report_user.reported_user_id', $id);
        $this->db->order_by('report_user.id', 'desc');
        $result = $this->db->get();
        return $result->result_array();
    }

    public function getReportedUserName($user_id){
        $this->db->select('users.first_name, users.last_name, users.image_url, users.blocked');
        $result = $this->db->get_where('users', ['id' => $user_id]);
        return $result->row_array();
    }

    public function blockUser($id){
        $this->db->update('users', ['blocked' => 'yes'], ['id' => $id]);
        return true;
    }

    public function unblockUser($id){
        $this->db->update('users', ['blocked' => 'no'], ['id' => $id]);
        return true;
    }

    public function getAdminUnseenNotification(){
        $this->db->select('notification.id');
        $result = $this->db->get_where('notification', ['status' => 'unseen']);
        return $result->num_rows();
    }

    public function getAdminNotification(){
        $this->db->select('notification.reported_user_id, notification.title, notification.message, notification.created_at, notification.status');
        $result = $this->db->get('notification');
        return $result->result_array();
    }

    public function getUserImage($user_id){
        $this->db->select('users.image_url');
        $result = $this->db->get_where('users', ['users.id' => $user_id]);
        return $result->row_array();
    }

    public function getUsersCount(){
        $this->db->select('*');
        $this->db->from('users');
        $this->db->where(['status !='=>'Deleted', 'first_name !='=>'', 'gender !='=>'']);
        return $this->db->get()->result_array();      
    }

    public function usersCountByGender($type){
        $this->db->select('*');
        $this->db->from('users');
        $this->db->where(['gender'=>$type]);
        return $this->db->get()->result_array();      
    }

    public function reportedUsersCount(){
        $this->db->select('*');
        $this->db->from('report_user');
        $this->db->group_by('reported_user_id');
        return $this->db->get()->result_array();     
    }

    public function getReligiousUsers($religion){
        $result = $this->db->get_where('users', ['religion' => $religion]);
        return $result->num_rows();
    }

    public function getBeliefSpecificationUsers($id){
        $result = $this->db->get_where('users', ['specify_religion' => $id]);
        return $result->num_rows();
    }

    public function getHabitsUsers($id){
        $result = $this->db->get_where('user_habits', ['habits' => $id]);
        return $result->num_rows();
    }
}
?>