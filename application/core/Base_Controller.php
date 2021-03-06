<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Base_Controller extends Common_Controller {

	public $form_data;
	//表名
	public $tb;
	//主键
	public $primary;
	//是否有addtime字段
	public $has_addtime;
	//登录的管理员信息
    public $admin_info;

	function __construct(){
		parent::__construct() ;
		$this->data['form_data'] = $this->form_data;

        //管理员信息
        $this->data['admin_info'] = $this->session->userdata('admin_info');
        $this->admin_info = $this->data['admin_info'];

		//管理员ID
		$admin_id = $this->admin_info['user_id'];
		$this->admin_id = $admin_id;
		//是否超级管理员
		$this->is_root = $this->admin_info['is_root'];

        //默认的头文件和尾文件
        $this->data['header'] = MANAGER_PATH.'/header';
        $this->data['footer'] = MANAGER_PATH.'/footer';
	}
	
	/**
	 * 检查管理员是否登录
	 * @param bool $toLogin 是否跳转到登录页
	 * @return boolean
	 */
	public function checkAdminLogin($toLogin = true){
		if($this->admin_id == ''){
			if($toLogin){
				redirect(MANAGER_PATH.'/Admin/login');
			}else{
				return false;
			}
		}else{
			$this->check_privileges($this->siteclass, $this->sitemethod);
		}
	}

	//检查后台帐号权限
	public function check_privileges($class, $method, $output = 'html'){
		$is_root = $this->admin_info['is_root'];
		$privileges = $this->getAdminPrivileges();
		$this->data['menus'] = $this->get_all_privileges_by_siteclass();
		$this->data['admin_menus'] = $this->get_all_privileges($privileges);
		if($is_root == '1'){
			$this->data['privileges'] = $this->data['menus'];
			return true;
		}
		if(strpos($method, 'public') !== false){
			return true;
		}
		$privileges = unserialize($privileges);
		$this->data['privileges'] = $privileges;
		$class_arr = isset($privileges[$class]) ? $privileges[$class] : array();
		$result = FALSE;
		if($class_arr){
			if(in_array($method, $class_arr)){
				$result = TRUE;
			}
		}
		
		if(!$result){
			if($output == 'html'){
				$this->message('对不起，您没有权限进行此操作。');
			}else{
				fail('对不起，您没有权限进行此操作。');
			}
		}
	}
	
	//得到当前管理员的权限
	public function getAdminPrivileges($user_id = 0){
		$user_id = $user_id ? $user_id : $this->admin_id;
		$privileges = $this->Result_model->getOneBySql("SELECT permissions FROM ".tname('admin_user')." WHERE user_id='{$user_id}'");
		$privileges = empty($privileges) ? '' : $privileges;
		return $privileges;
	}


	//得到adminmenu.php中所有权限
	public function get_all_privileges($privileges = array()){
		//include APPPATH."config/adminmenu.php";
        /**
         * 2017-06-21进行更改
         * 将权限节点分别填入到config/adminmenu/*.php
         * 再将每个节点进行合并进行管理
         */
        $admin_privileges = array();
        $menu = $this->get_admin_menu();

        foreach ($menu as $key => $val){
			$admin_privileges[$key] = $val;
		}

		$normal_menu_arr = array();
		if(!$this->is_root && !empty($privileges)){
			$privileges = unserialize($privileges);
			foreach( $admin_privileges as $k => $r){
				if($r['status'] == 0) continue;
				if(!empty($r['lists'])){
					foreach( $r['lists'] as $subk => $subr){
						if(isset($privileges[$subk])){
							if(!isset($normal_menu_arr[$r['id']])){
								$normal_menu_arr[$r['id']] = $r;
							}
						}
					}
				}
			}
			$admin_privileges = $normal_menu_arr;
		}

		return $admin_privileges;
	}
	
	//以siteclass为键，得到权限节点
	public function get_all_privileges_by_siteclass(){
		$menu = $this->get_admin_menu();
		$privileges = array();
		foreach ($menu as $key => $val){
			$row = array();
			$row = $val['lists'];
			foreach($row as $k => $r){
				$privileges[$k] = $r['method'];
				$privileges[$k]['topmenu'] = $r['name'];
			}
		}
		return $privileges;
	}

    //得到所有定义的权限节点
	private function get_admin_menu(){
        $menu_folder = APPPATH . 'config/adminmenu';
        $menu_files = glob($menu_folder. '/*.php');
        $menu_array = array();
        foreach($menu_files as $k => $file){
            include $file;
            $menu_array[] = @$config;
        }
        return $menu_array;
    }

    /**
	 * 前台提示信息
	 * @param string $err   输出信息
	 * @param string $url  跳转到URL
	 * @param int $sec  跳转秒数
	 * @param int $is_right 是否是正确的时候显示的信息
	 */
	public function message( $err ='', $url='', $sec = '1' , $is_right = 0){
		if( $err ){
			$this->data['sec'] = $sec*1000;
			$this->data['url'] = reMoveXss($url);
			$this->data['err'] = reMoveXss($err);
			$this->load->view(MANAGER_PATH.'/message', $this->data);
		}
	}

	/**
	 * 公共删除方法
	 */
	public function commonDelete($redirect = true){
		$data = !_post('ids') ? _get('id') : _post();
		if(!empty($data)){
			$model = !empty($tb) ? $tb : $this->tb;
			//删除方法
			$delete_method = 'delete';
			//检查权限
			$this->check_privileges($model, $delete_method);
			$ids = !empty($data['ids']) ? $data['ids'] : $data;
			if(!is_array($ids)){
				$ids = array($ids);
			}
			$primary_key = $this->primary;
			$res = false;
			if(!empty($ids)){
				foreach($ids as $k => $id){
					$where = array($primary_key => $id);
					$res = $this->Result_model->delete(strtolower($model), $where);
				}
				if(!$redirect ){
					return $res;
				}else{
					$this->message('删除成功', HTTP_REFERER);
				}
			}
		}
	}

	/**
	 * [批量排序]
	 * @$primary_key 主键
	 * $order_field 排序字段
	 * @date 2015-5-12
	 **/
	public function commonListOrder($order_field){
		$data = _post('listorder') ? _post('listorder') : array();
		$model = $this->siteclass;
		if($data){
			foreach($data as $k => $v){
				$data = array($order_field => !empty($v) ? $v : 0);
				$where = array($this->primary => $k);
				$this->Result_model->update(strtolower($model),$where, $data);
			}
			$this->message('排序成功', HTTP_REFERER);
		}
	}


   /**
    * 保存数据 
    * @param  [array] $row 要进行验证的数据
    * @param  [string] $callback   回调函数
    */
    protected function saveData($row = array(), $callback = ''){
        $post = _post('data');
        $row = !empty($post) ? $post : $row;
		$data = $this->form_data;
		$tb = $this->tb;
		$primary = 'id';
        if(!empty($post)){
			//验证规则
			foreach($data as $name => $r){
				if(!empty($r['is_primary'])){
				    $primary = $name;
					continue;
				}
				$rule = 'trim|required';
				if(!empty($r['rule'])){
					$rule = $r['rule'];
				}
				if(!empty($r['append_rule'])){
					$rule .= '|'.$r['rule'];
				}
				if(empty($r['is_require'])){
                    $rule = 'trim';
                }
				if(!empty($r['field'])){
					$this->form_validation->set_rules('data['.$name.']', $r['field'], $rule);
				}
			}
			//验证成功， 保存
			if($this->form_validation->run() !== FALSE){
				foreach ($data as $name => $title) {
					$save_data[$name] = $post[$name];
					if(is_array($post[$name])){
						$save_data[$name] = implode(',', $post[$name]);
					}
				}
				if(!empty($this->has_addtime)){
					$save_data['addtime'] = date('Y-m-d H:i:s');
				}
				$res = $this->Result_model->save($tb, $save_data, $primary);
				if($res){
					if(!empty($callback)){
                        $callback($res);//执行回调函数
                    }else{
                        url_to();
                    }
				}
			}else{

			}
		}
		$this->data['row'] = $row;
		$this->_renderForm($this->data['row']);
	}

	//得到记录
	public function getRow($id, $field = '*'){
        $this->load->model('Check_model');
        $row = $this->Check_model->checkRow($this->tb, $this->primary, $id, $field);
		$this->data['row'] = $row;
		return $row;
	}

	//渲染HTML表单
	public function _renderForm($row = array()){
		$form_html = createFormHtml($this->form_data, $row);
		$vars = array(
			'form_html' => $form_html,
		);
		$this->tpl->assign($vars);
	}
}

