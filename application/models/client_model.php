<?php
class Client_model extends SS_Model{
	function __construct(){
		parent::__construct();
	}
	
	function fetch($id,$data_type='array'){
		$query="SELECT * FROM client WHERE id='".$id."'";
		$data=db_fetch_first($query,true);
		if(empty($data)){
			return false;
	
		}elseif($data_type=='array'){
			return $data;
	
		}elseif(isset($data[$data_type])){
			return $data[$data_type];
		}
		
	}
	
	function check($client_name,$data_type='id',$show_error=true,$fuzzy=true){
		//$data_type:id,array
		
		global $_G;
		
		if(!$client_name){
			if($show_error){
				showMessage('请输入客户名称','warning');
			}
			return -3;
		}
	
		if($fuzzy){
			$q_client="SELECT * FROM `client` WHERE display=1 AND company='".$_G['company']."' AND (`name` LIKE '%".$client_name."%' OR abbreviation LIKE '".$client_name."')";
		}else{
			$q_client="SELECT * FROM `client` WHERE display=1 AND company='".$_G['company']."' AND (`name` LIKE '".$client_name."' OR abbreviation LIKE '".$client_name."')";
		}
		$r_client=db_query($q_client);
		$num_clients=db_rows($r_client);
	
		if($num_clients==0){
			if($show_error){
				showMessage('没有这个客户：'.$client_name,'warning');
			}
			return -1;
			
		}elseif($num_clients>1){
			if($show_error){
				showMessage('此关键词存在多个符合客户','warning');
			}
			return -2;
	
		}else{
			$data=db_fetch_array($r_client);
			if($data_type=='array'){
				$return=$data;
			}else{
				$return=$data[$data_type];
			}
			
			return $return;
		}
	}
	
	function add($data){
		$field=array('name','character','classification','type','abbreviation','source','source_lawyer','comment','work_for');
		foreach($data as $key => $value){
			if(!in_array($key,$field)){
				unset($data[$key]);
			}
		}
		
		$data['abbreviation']=$data['name'];
	
		$data['display']=1;
		$data+=uidTime();
	
		return db_insert('client',$data);
	}
	
	function addRelated($data){
		return db_insert('client_client',$data);
	}
	
	function addContact($data){
		return db_insert('client_contact',$data);
	}
	
	function addSource($data){
		return db_insert('client_source',$data);
	}
	
	function addContact_phone_email($client,$phone,$email){
		$new_client_contact=array();
		if($phone){
			$new_client_contact[]=array(
				'client'=>$client,
				'type'=>isMobileNumber($phone)?'手机':'固定电话',
				'content'=>$phone,
			);
		}
		if($email){
			$new_client_contact[]=array(
				'client'=>$client,
				'type'=>'电子邮件',
				'content'=>$email,
			);
		}
		db_multiinsert('client_contact',$new_client_contact);
	}
	
	function setDefaultRelated($client_client_id,$client){
		client_clearDefaultRelated($client);
		
		if(db_update('client_client',array('is_default_contact'=>1),"id='".$client_client_id."'")){
			return true;
		}
		return false;
	}
	
	function clearDefaultRelated($client){
		if(db_update('client_client',array('is_default_contact'=>'_NULL_'),"client_left='".$client."'")){
			return true;
		}
		return false;
	}
	
	function delete($client_id){
		if(is_array($client_id)){
			$condition = db_implode($client_id, $glue = ' OR ','id','=',"'","'", '`','key');
	
		}elseif(is_int($client_id)){
			$condition = "id = '".$client_id."'";
	
		}else{
			return false;
		}
		return db_delete('client',$condition);
	}
	
	function deleteRelated($client_clients){
		$condition = db_implode($client_clients, $glue = ' OR ','id','=',"'","'", '`','key');
		db_delete('client_client',$condition);
	}
	
	function deleteContact($client_contacts){
		$condition = db_implode(post('client_contact_check'), $glue = ' OR ','id','=',"'","'", '`','key');
		db_delete('client_contact',$condition);
	}
	
	function checkSource($detail,$checktype){
		if($checktype=='client'){
			$q_source="SELECT id FROM client_source WHERE client='".intval($detail)."' LIMIT 1";
			
		}elseif($checktype=='staff'){
			$q_source="SELECT id FROM client_source WHERE staff='".intval($detail)."' LIMIT 1";
	
		}else{
			$q_source="SELECT id FROM client_source WHERE type='".$checktype."' AND detail='".$detail."' LIMIT 1";
		}
		
		if($client_source_id=db_fetch_field($q_source)){
			return $client_source_id;
		}else{
			return false;
		}
	}
	
	function setSource($type,$detail){
		
		if(!$type){
			showMessage('请选择客户来源','warning');
			return false;
		}
	
		if($type=='老客户介绍'){
			$client_check=client_check($detail,'array');
			if($client_check<0){
				return false;
			}else{
				post('source/client',$client_check['id']);
				post('source/detail',$client_check['abbreviation']);
			}
			$client_source=$this->checkSource(post('source/client'),'client');
	
		}else{
			$client_source=$this->checkSource($detail,$type);
		}
		//试图获得现存"来源"的ID
		if($client_source===false){
			//插入一种新来源
			$client_source_array=array(
				'type'=>$type,
				'detail'=>$detail
			);
			if(in_array($client_source_array['type'],array('其他网络','媒体','老客户介绍','中介机构介绍','其他'))){
				$client_source_array['detail']=post('source/detail');
				if($client_source_array['type']=='老客户介绍'){
					$client_source_array['client']=post('source/client');
				}
			}else{
				post('source/detail','');
			}
			$client_source=client_addSource($client_source_array);
		}
		
		return $client_source;
	}
	
	function fetchSource($source_id){
		return db_fetch_first("SELECT type,detail FROM client_source WHERE id='".$source_id."'");
	}
	
	function getListByCase($case_id){
		//根据相关案件获得客户列表
		$option_array=array();
		
		$q_option_array="SELECT id,abbreviation FROM client WHERE display=1 AND classification='客户'";
		if($case_id>20){
			$q_option_array.=" AND id IN (SELECT client FROM case_client WHERE `case`='".$case_id."')";
		
		}elseif($case_id==11){
			//潜在客户维护
			$q_option_array.=" AND type='潜在客户' AND id NOT IN (SELECT client FROM case_client)";
	
		}elseif($case_id==12){
			//老客户维护
			$q_option_array.=" AND type='成交客户' 
				AND id IN (
					SELECT client FROM case_client WHERE `case` IN (
						SELECT id FROM `case` WHERE filed=1
							AND id IN (
								SELECT `case` FROM case_lawyer 
								WHERE lawyer='".$_SESSION['id']."' OR uid='".$_SESSION['id']."'
							)
					)
			)";
	
		}elseif($case_id==13){
			//咨询跟踪
			$q_option_array.="
				AND id IN (SELECT client FROM query WHERE display=1 AND filed=0 AND (partner='".$_SESSION['id']."' OR lawyer='".$_SESSION['id']."' OR assistant='".$_SESSION['id']."'))
			";
		}
		$q_option_array.=" ORDER BY id DESC";
		
		$option_array=db_toArray($q_option_array);
		$option_array=array_sub($option_array,'abbreviation','id');
	
		return $option_array;	
	}
	
	function match($part_of_name,$classification='client'){
		$query="SELECT id,name FROM client WHERE display=1 AND name LIKE '%".$part_of_name."%' OR abbreviation LIKE '".$part_of_name."'OR name_en LIKE '%".$part_of_name."%'";
		
		switch($classification){
			case 'client': $query.=" AND classification='客户'";break;
			case 'contact': $query.=" AND classification='联系人'";break;
			case 'opposite': $query.=" AND classification='相对方'";break;
		}
		
		$query.=" ORDER BY time DESC";
		
		$client_array=db_toArray($query);
		
		return $client_array;
	}
        
        function getList($method=NULL){
  		$q="
			SELECT client.id,client.name,client.abbreviation,client.time,client.comment,
				phone.content AS phone,address.content AS address
			FROM `client` 
				LEFT JOIN (
					SELECT client,GROUP_CONCAT(content) AS content FROM client_contact WHERE type IN('手机','固定电话') GROUP BY client
				)phone ON client.id=phone.client
				LEFT JOIN (
					SELECT client,GROUP_CONCAT(content) AS content FROM client_contact WHERE type='地址' GROUP BY client
				)address ON client.id=address.client
			WHERE display=1 AND classification='客户'
		";
		$q_rows="
			SELECT COUNT(client.id)
			FROM `client` 
			WHERE display=1 AND classification='客户'
		";
		$condition='';
		if($method=='potential'){
			$condition.=" AND type='潜在客户'";
		
		}else{
			$condition.="
				AND type='成交客户'
				AND client.id IN (SELECT client FROM case_client WHERE `case` IN (SELECT `case` FROM case_lawyer WHERE lawyer='".$_SESSION['id']."'))
		";
		}
                $this->session->set_userdata('last_list_action',$_SERVER['REQUEST_URI']);
                $search_fields=array('name'=>'姓名','work_for'=>'单位','address'=>'地址','comment'=>'备注');                
                $condition=$this->search($condition,$search_fields);
                $condition=$this->orderBy($condition,'time','DESC',array('abbreviation','type','address','comment'));
                $q.=$condition;
                $q_rows.=$condition;
                $this->pagination($q,$q_rows);
                return $this->db->query($q)->result_array();
        }
}
?>