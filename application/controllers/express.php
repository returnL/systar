<?php
class Express extends SS_controller{

	function __construct(){
		parent::__construct();
	}
	
	function lists(){
		$field=array(
			'content'=>array('title'=>'寄送内容','surround'=>array('mark'=>'a','href'=>'/express/edit/{id}'),'td'=>'class="ellipsis" title="{content}"'),
			'time_send'=>array('title'=>'日期','td_title'=>'width="60px"','eval'=>true,'content'=>"
				return date('m-d',{time_send});
			"),
			'sender_name'=>array('title'=>'寄送人'),
			'destination'=>array('title'=>'寄送地点','td'=>'class="ellipsis" title="{destination}"'),
			'num'=>array('title'=>'单号'),
			'comment'=>array('title'=>'备注')
		);
		
		$table=$this->table->setFields($field)
			->setData($this->express->getList())
			->generate();
		
		$this->load->addViewData('list',$table);

		$this->load->view('list');
	}
	
	function add(){
		$this->edit();
	}
	
	function edit($id=NULL){
		$this->load->model('staff_model','staff');
		
		$this->getPostData($id,function($CI){
			post('express/time_send',$CI->config->item('timestamp'));
		});
		
		post('express_extra/sender_name',$this->staff->fetch(post('express/sender'),'name'));
		
		post('express_extra/time_send',date('Y-m-d',post('express/time_send')));
		
		$submitable=false;//可提交性，false则显示form，true则可以跳转
		
		if(is_posted('submit')){
			$submitable=true;
			$_SESSION[CONTROLLER]['post']=array_replace_recursive($_SESSION[CONTROLLER]['post'],$_POST);
			
			//将寄件人姓名转换成staff.id
			$staff_check=$this->staff->check(post('express_extra/sender_name'),'array');
			if($staff_check<0){
				$submitable=false;

			}else{
				post('express/sender',$staff_check['id']);
				post('express_extra/sender_name',$staff_check['name']);
			}
			
			//将时间转换成timestamp格式
			if(strtotime(post('express_extra/time_send'))){
				post('express/time_send',strtotime(post('express_extra/time_send')));
			}else{
				$submitable=false;
				showMessage('寄送日期格式错误','warning');
			}
			
			$this->processSubmit($submitable);
		}
	}
}
?>