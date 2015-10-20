<?php
if( !defined('IN') ) die('bad request');
include_once( AROOT . 'controller'.DS.'app.class.php' );

class dashboardController extends appController
{
	function __construct()
	{
		parent::__construct();
		$this->check_login();
	}
	
	function index()
	{
		$data['title'] = $data['top_title'] = 'TODO';
		render( $data , 'web' , 'card' );
	}

	function check_version()
	{
		$params = array();

		if($content = send_request( 'check_new_verison' ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			return render( array( 'code' => 0 , 'data' => $data['data'] ) , 'rest' );
		}

		return render( array( 'code' => 100001 , 'message' => 'can not get api content' ) , 'rest' );
	}

	function user_reset_password()
	{
		$params = array();
		$params['uid'] = intval(v('uid'));

		
		if($content = send_request( 'user_reset_password' ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			return render( array( 'code' => 0 , 'data' => $data['data'] ) , 'rest' );
		}

		return render( array( 'code' => 100001 , 'message' => 'can not get api content' ) , 'rest' );
	}

	function user_tooltips()
	{
		$params = array();
		$params['uid'] = intval(v('uid'));

		
		if($content = send_request( 'user_profile' ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			if( intval($data['err_code']) != 0 ) 
				return ajax_echo( '数据载入失败，请稍后再试' );
			else
				return render( $data , 'ajax' , 'raw'  );
		}
	}

	function dbup()
	{
		$sql = "ALTER TABLE  `feed` ADD  `comment_count` int(11) NOT NULL DEFAULT '0' ";
			run_sql( $sql );

		$sql = "ALTER TABLE  `comment` ADD  `device` varchar(16) NOT NULL ";
		run_sql( $sql );	

		$sql = "ALTER TABLE  `keyvalue` CHANGE  `key`  `key` VARCHAR( 64 ) NOT NULL";
		run_sql( $sql );

		$sql = "ALTER TABLE  `user` ADD  `groups` VARCHAR( 255 ) NOT NULL AFTER  `desp` ,
ADD INDEX (  `groups` )";
		run_sql( $sql );
	
		

		$sql = "CREATE TABLE IF NOT EXISTS `online` (
  `uid` int(11) NOT NULL,
  `last_active` datetime NOT NULL,
  `session` varchar(32) NOT NULL,
  `device` varchar(32) DEFAULT NULL,
  `place` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`uid`),
  KEY `last_active` (`last_active`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";
		
		run_sql( $sql );

		$sql = "CREATE TABLE IF NOT EXISTS  `plugin` (
`folder_name` VARCHAR( 32 ) NOT NULL ,
`on` TINYINT( 1 ) NOT NULL DEFAULT  '0',
PRIMARY KEY (  `folder_name` )
) ENGINE = MYISAM DEFAULT CHARSET=utf8";
	
		run_sql( $sql );

		$sql = "ALTER TABLE  `todo` ADD  `comment_count` INT NOT NULL DEFAULT  '0' ";
		run_sql( $sql );
		
		
		return info_page('更新完成，请<a href="?c=dashboard">用力刷新页面以保证新代码正常工作</a>');	
		
	}

	function upgrade()
	{
		if(!is_admin()) return info_page( '只有管理员才能进行升级' );

		$params = array();
		if($content = send_request( 'upgrade' ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			if( intval($data['err_code']) != 0 )
			{
				if( $data['err_code'] == '10011' )
					return info_page( '已经是最新版本' );
				else
					return info_page( '升级失败，请稍后再试' );
			} 
			else
			{
				if( $data['data']['pscript'] )
					return info_page( '代码更新成功，请<a href="' . $data['data']['pscript'] . '">点击这里升级数据表</a>' );
				else	
					return info_page( '成功更新，请<a href="/">用力刷新页面以保证新代码正常工作</a>' );
			}
				
		}
		return info_page( '联网失败，请稍后再试' );
	}

	function get_fresh_chat()
	{
		$uid = intval(v('uid'));
		if( $uid< 1 ) return  null;

		$params = array();
		$params['uid'] = $uid;
		
		if($content = send_request( 'get_fresh_chat' ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			if( intval($data['err_code']) != 0 ) 
				return false;

			$data['data']['items'] = array_reverse($data['data']['items']);
			return render( $data , 'ajax' , 'raw'  );

		}

		return null;
	}

	function im_history()
	{
		$params = array();
		$params['max_id'] = intval(v('max_id'));
		$params['since_id'] = intval(v('since_id'));
		
		// mark all chat as read so we can list it in "history"
		$content = send_request( 'get_fresh_chat' ,  $params , token() );
		if($content = send_request( 'im_history' ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			if( intval($data['err_code']) != 0 ) 
				return false;

			$data['data']['items'] = array_reverse($data['data']['items']);
			return render( $data , 'ajax' , 'raw'  );

		}

		return null;
		

	}

	function im_send()
	{
		$uid = intval(v('uid'));
		if( $uid< 1 ) return  render( array( 'code' => 100002 , 'message' => 'bad args,uid required' ) , 'rest' );
		
		$text = z(t(v('text')));
		if( strlen($text) < 1 ) return  render( array( 'code' => 100002 , 'message' => 'bad args,text required' ) , 'rest' );
		
		$params = array();
		$params['uid'] = $uid;
		$params['text'] = $text;


		if($content = send_request( 'im_send' ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			if( $data['err_code'] == 0 )
				return render( array( 'code' => 0 , 'data' =>  array( 'html' => render_html( 
					array( 'item' => array( 'content'  => $text , 'from_uid' => uid() , 'timeline' => date("Y-m-d H:i:s") ) ) , AROOT . 'view' 
						. DS . 'layout' . DS . 'ajax' . DS . 'widget' . DS . 'im_history.tpl.html'  ) ) ) , 'rest' );
			else
				return render( array( 'code' => $data['err_code'] , 'message' => $data['err_msg'] ) , 'rest' );
		}

		return render( array( 'code' => 100001 , 'message' => 'can not get api content' ) , 'rest' );
  
	}

	function im_buddy_list()
	{
		$params = array();
		
		if($content = send_request( 'team_members' ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			if( $data['err_code'] == 0 )
			{
				return render( array( 'code' => 0 , 'data' =>  array( 'html' => render_html( array( 'items' => $data['data'] ) , AROOT . 'view' 
						. DS . 'layout' . DS . 'ajax' . DS . 'widget' . DS . 'im.tpl.html'  ) ) ) , 'rest' );
			}
			else
				return render( array( 'code' => 100002 , 'message' => 'can not save data' ) , 'rest' );
		}
	}

	function im_buddy_box()
	{
		$params = array();
		$params['uid'] = intval(v('uid'));

		if($content = send_request( 'user_profile' ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			if( $data['err_code'] == 0 )
			{
				return render( array( 'code' => 0 , 'data' =>  array( 'html' => render_html( array( 'item' => $data['data'] ) , AROOT . 'view' 
						. DS . 'layout' . DS . 'ajax' . DS . 'widget' . DS . 'imbox.tpl.html'  ) ) ) , 'rest' );
			}
			else
				return render( array( 'code' => 100002 , 'message' => 'can not save data' ) , 'rest' );
		}
	}

	function profile()
	{
		
		$params = array();
		$params['uid'] = uid();

		
		if($content = send_request( 'user_profile' ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			if( intval($data['err_code']) != 0 ) 
				return ajax_echo( '数据载入失败，请稍后再试' );
			else
				return render( $data , 'ajax' , 'raw'  );
		}

	}

	function avatar()
	{
		return render( $data , 'ajax' , 'raw'  );
	}

	function update_avatar()
	{
		$x = intval(v('x'));
		$y = intval(v('y'));
		$w = $h = intval(v('w'));
		$targ_w = $targ_h = 100;
	
	
		if( $_FILES['ufile']['error'] != 0 )
			return info_page('文件上传错误，请重新上传');
		
		if( $w == 0 || $h == 0 )
		{
			$tmp_name = $_FILES['ufile']['tmp_name'];
		}
		else
		{
			// do crop
			$src = $_FILES['ufile']['tmp_name'];

			if( $_FILES['ufile']['type'] == 'image/png' )
				$img_r = imagecreatefrompng($src);
			elseif( $_FILES['ufile']['type'] == 'image/gif' )
				$img_r = ImageCreateFromGIF($src);
			else
				$img_r = imagecreatefromjpeg($src);

			$dst_r = ImageCreateTrueColor( $targ_w, $targ_h );
			
			imagecopyresampled($dst_r,$img_r,0,0, $x  ,$y ,$targ_w,$targ_h,$w,$h);
			
			$tmp_name = SAE_TMP_PATH.uid().'-avatar';
			imagejpeg( $dst_r , $tmp_name , 90 );

		}


		

		$data['token'] = token();
		$data['file'] = '@'.$tmp_name;

		if($content = upload_as_form( c('api_server').'?c=api&a=user_update_avatar' , $data ))
		{
			$data = json_decode($content , 1);
			if( $data['err_code'] == 0 )
				return info_page('<a href="?c=buddy">头像更新成功，由于浏览器缓存的关系，您可能看到的还是旧头像，可强制刷新或清空缓存。</a>' );
			else
				return info_page('头像更新失败，错误码-'.$data['err_code'].'，错误信息-'.$data['err_msg']);
		}
	}

	function password()
	{
		return render( $data , 'ajax' , 'raw'  );
	}

	function update_password()
	{
		$opassword = z(t(v('oldpassword')));
		if( strlen($opassword) < 1 ) return  render( array( 'code' => 100002 , 'message' => 'bad args,old password required' ) , 'rest' );
		
		$password = z(t(v('newpassword')));
		if( strlen($password) < 1 ) return  render( array( 'code' => 100002 , 'message' => 'bad args,new password required' ) , 'rest' );
		

		$params = array();
		$params['opassword'] = $opassword;
		$params['password'] = $password;
		

		if($content = send_request( 'user_update_password' ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			if( $data['err_code'] == 0 )
				return render( array( 'code' => 0 , 'data' => $data['data']) , 'rest' );
			else
				return render( array( 'code' => $data['err_code'] , 'message' => $data['err_msg'] ) , 'rest' );
		}

		return render( array( 'code' => 100001 , 'message' => 'can not get api content' ) , 'rest' );
	}



	function update_profile()
	{
		$email = z(t(v('email')));
		if( strlen($email) < 1 ) return  render( array( 'code' => 100002 , 'message' => 'bad args,email required' ) , 'rest' );
		
		$mobile = z(t(v('mobile')));
		if( strlen($mobile) < 1 ) return  render( array( 'code' => 100002 , 'message' => 'bad args,mobile required' ) , 'rest' );
		

		$params = array();
		$params['mobile'] = $mobile;
		$params['tel'] = z(t(v('tel')));
		$params['eid'] = z(t(v('eid')));
		$params['weibo'] = z(t(v('weibo')));
		$params['desp'] = z(t(v('desp')));
		$params['email'] = $email;
		
		

		if($content = send_request( 'user_update_profile' ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			return render( array( 'code' => 0 , 'data' => $data['data'] ) , 'rest' );
		}

		return render( array( 'code' => 100001 , 'message' => 'can not get api content' ) , 'rest' );
	}

	function user_unread()
	{
		$params = array();

		if($content = send_request( 'user_unread' ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			return render( array( 'code' => 0 , 'data' => $data['data'] ) , 'rest' );
		}

		return render( array( 'code' => 100001 , 'message' => 'can not get api content' ) , 'rest' );
	}

	function user_online()
	{
		$params = array();

		if($content = send_request( 'user_online' ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			return render( array( 'code' => 0 , 'data' => $data['data'] ) , 'rest' );
		}

		return render( array( 'code' => 100001 , 'message' => 'can not get api content' ) , 'rest' );
	}

	function people_box()
	{
		
		$params = array();

		if($content = send_request( 'team_members' ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			if( intval($data['err_code']) != 0 ) 
				return ajax_echo( '数据载入失败，请稍后再试' );
			else
			{
				$data['tid'] = intval(v('tid'));
				return render( $data , 'ajax' , 'raw'  );
			}
				
		}

	}

	function todo_edit()
	{
		$tid = intval(v('tid'));
		if( $tid < 1 ) return ajax_echo('错误的TID，请点击左侧TODO重新载入后重试');

		$text = z(t(v('text')));
		if( strlen($text) < 1 ) return ajax_echo('错误的TODO标题，请点击左侧TODO重新载入后重试');

		$data['tid'] = $tid;
		$data['text'] = $text;

		return render( $data , 'ajax' , 'raw' );

	}

	function todo_assign()
	{
		// todo_assign
		$tid = intval(v('tid'));
		if( $tid < 1 ) return render( array( 'code' => 100002 , 'message' => 'bad args' ) , 'rest' );
		
		$uid = intval(v('uid'));
		if( $uid < 1 ) return render( array( 'code' => 100002 , 'message' => 'bad args' ) , 'rest' );

		$params = array();
		$params['tid'] = $tid;
		$params['uid'] = $uid;

		if($content = send_request( 'todo_assign' ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			return render( array( 'code' => 0 , 'data' => $data['data'] ) , 'rest' );
		}

		return render( array( 'code' => 100001 , 'message' => 'can not get api content' ) , 'rest' );




	}

	function todo_detail()
	{

		//return ajax_echo( print_r( $_REQUEST , 1 ) );
		$tid = intval(v('tid'));
		if( $tid < 1 ) return info_page('加载todo失败，请重试');

		$params = array();
		$params['tid'] = $tid;
		
		if($content = send_request( 'todo_detail' ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			if( intval($data['err_code']) != 0 ) 
				return false;
			else
				return render( $data , 'ajax' , 'raw'  );
		}

		return info_page('加载todo失败，请重试');

		
	}

	function todo_start()
	{
		$tid = intval(v('tid'));
		if( $tid < 1 ) return render( array( 'code' => 100002 , 'message' => 'bad args' ) , 'rest' );
		
		if( t(v('type')) == 'pause' ) $action = 'todo_pause';
		else $action = 'todo_start';

		$params = array();
		$params['tid'] = $tid;

		if($content = send_request( $action ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			return render( array( 'code' => 0 , 'data' => $data['data'] ) , 'rest' );
		}

		return render( array( 'code' => 100001 , 'message' => 'can not get api content' ) , 'rest' );

	}

	function todo_public()
	{
		$tid = intval(v('tid'));
		if( $tid < 1 ) return render( array( 'code' => 100002 , 'message' => 'bad args' ) , 'rest' );
		
		if( t(v('type')) == 'private' ) $action = 'todo_private';
		else $action = 'todo_public';

		$params = array();
		$params['tid'] = $tid;

		if($content = send_request( $action ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			return render( array( 'code' => 0 , 'data' => $data['data'] ) , 'rest' );
		}

		return render( array( 'code' => 100001 , 'message' => 'can not get api content' ) , 'rest' );

	}

	function todo_star()
	{
		$tid = intval(v('tid'));
		if( $tid < 1 ) return render( array( 'code' => 100002 , 'message' => 'bad args' ) , 'rest' );
		
		if( t(v('type')) == 'remove' ) $action = 'todo_unstar';
		else $action = 'todo_star';

		$params = array();
		$params['tid'] = $tid;

		if($content = send_request( $action ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			return render( array( 'code' => 0 , 'data' => $data['data'] ) , 'rest' );
		}

		return render( array( 'code' => 100001 , 'message' => 'can not get api content' ) , 'rest' );
	}

	function todo_follow()
	{
		$tid = intval(v('tid'));
		if( $tid < 1 ) return render( array( 'code' => 100002 , 'message' => 'bad args' ) , 'rest' );

		$params = array();
		$params['tid'] = $tid;
		
		if(t(v('type'))=='follow') $action = 'todo_follow';
		else $action = 'todo_unfollow';

		if($content = send_request( $action ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			return render( array( 'code' => 0 , 'data' => $data['data'] ) , 'rest' );
		}

		return render( array( 'code' => 100001 , 'message' => 'can not get api content' ) , 'rest' );
	}

	function todo_done()
	{
		$tid = intval(v('tid'));
		if( $tid < 1 ) return render( array( 'code' => 100002 , 'message' => 'bad args' ) , 'rest' );

		$params = array();
		$params['tid'] = $tid;
		
		if(t(v('action'))=='undo') $action = 'todo_reopen';
		else $action = 'todo_done';

		if($content = send_request( $action ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			return render( array( 'code' => 0 , 'data' => $data['data'] ) , 'rest' );
		}

		return render( array( 'code' => 100001 , 'message' => 'can not get api content' ) , 'rest' );
	}

	function todo_reopen()
	{
		$_REQUEST['action'] = 'undo';
		return $this->todo_done();
	}

	function todo_remove_comment()
	{
		$hid = intval(v('hid'));
		if( $hid < 1 ) return render( array( 'code' => 100002 , 'message' => 'bad args' ) , 'rest' );

		$params = array();
		$params['hid'] = $hid;

		if($content = send_request( 'todo_remove_comment' ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			if( $data['err_code'] == 0 )
			{
				return render( array( 'code' => 0 , 'data' => $data['data']) , 'rest' );
			}
			else
				return render( array( 'code' => 100002 , 'message' => 'can not save data' ) , 'rest' );
			//return render( array( 'code' => 0 , 'data' => $data['data'] ) , 'rest' );
		}

		return render( array( 'code' => 100001 , 'message' => 'can not get api content' ) , 'rest' );

	}

	function todo_all_done()
	{
		//todo_remove_done
		$params = array();
		if($content = send_request( 'todo_all_done' ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			if( $data['err_code'] == 0 )
			{
				return render( array( 'code' => 0 , 'data' => $data['data']) , 'rest' );
			}
			else
				return render( array( 'code' => 100002 , 'message' => 'can not save data' ) , 'rest' );
		}

		return render( array( 'code' => 100001 , 'message' => 'can not get api content' ) , 'rest' );
	}

	function todo_clean()
	{
		//todo_remove_done
		$params = array();
		if($content = send_request( 'todo_remove_done' ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			if( $data['err_code'] == 0 )
			{
				return render( array( 'code' => 0 , 'data' => $data['data']) , 'rest' );
			}
			else
				return render( array( 'code' => 100002 , 'message' => 'can not save data' ) , 'rest' );
		}

		return render( array( 'code' => 100001 , 'message' => 'can not get api content' ) , 'rest' );
	}

	function todo_add_comment()
	{
		$text = z(t(v('text')));
		if( strlen( $text ) < 1 ) return render( array( 'code' => 100002 , 'message' => 'bad args' ) , 'rest' );

		$tid = intval(v('tid'));
		if( $tid < 1 ) return render( array( 'code' => 100002 , 'message' => 'bad args' ) , 'rest' );

		$params = array();
		$params['text'] = $text;
		$params['tid'] = $tid;

		if($content = send_request( 'todo_add_comment' ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			if( $data['err_code'] == 0 )
			{
				return render( array( 'code' => 0 , 'data' =>  array( 'html' => render_html( array( 'item' => $data['data'] ) , AROOT . 'view' 
						. DS . 'layout' . DS . 'ajax' . DS . 'widget' . DS . 'history.tpl.html'  ) ) ) , 'rest' );
			}
			else
				return render( array( 'code' => 100002 , 'message' => 'can not save data' ) , 'rest' );
			//return render( array( 'code' => 0 , 'data' => $data['data'] ) , 'rest' );
		}

		return render( array( 'code' => 100001 , 'message' => 'can not get api content' ) , 'rest' );
	}

	function todo_update()
	{
		$text = z(t(v('text')));
		if( strlen( $text ) < 1 ) render( array( 'code' => 100002 , 'message' => 'bad args' ) , 'rest' );

		$tid = intval(v('tid'));
		if( $tid < 1 ) return render( array( 'code' => 100002 , 'message' => 'bad args' ) , 'rest' );

		$params = array();
		$params['text'] = $text;
		$params['tid'] = $tid;

		if($content = send_request( 'todo_update' ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			if( $data['err_code'] == 0 )
			{
				$data['data']['is_public'] = $data['data']['details']['is_public'];
				return render( array( 'code' => 0 , 'data' =>  array( 'html' => render_html( array( 'item' => $data['data'] ) , AROOT . 'view' 
						. DS . 'layout' . DS . 'ajax' . DS . 'widget' . DS . 'todo.tpl.html'  ) ) ) , 'rest' );
			}
			else
				return render( array( 'code' => 100002 , 'message' => 'can not save data' ) , 'rest' );
			//return render( array( 'code' => 0 , 'data' => $data['data'] ) , 'rest' );
		}

		return render( array( 'code' => 100001 , 'message' => 'can not get api content' ) , 'rest' );

	}

	function todo_add()
	{
		$text = z(t(v('text')));
		if( strlen( $text ) < 1 ) render( array( 'code' => 100002 , 'message' => 'bad args' ) , 'rest' );

		$params = array();
		$params['text'] = $text;
		$params['is_public'] = intval(v('is_public'));
		$params['uid'] = intval(v('uid'));
		
		
		if($content = send_request( 'todo_add' ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			if( $data['err_code'] == 0 )
			{
				// 
				$tid = intval($data['data']['id']) ;
				if( ($tid > 0) && (intval(v('is_star')) == 1) )
					send_request( 'todo_star' ,  array( 'tid' => $tid ) , token()  );



				$data['data']['is_public'] = $data['data']['details']['is_public'];
				return render( array( 'code' => 0 , 'data' =>  array( 'html' => render_html( array( 'item' => $data['data'] ) , AROOT . 'view' 
						. DS . 'layout' . DS . 'ajax' . DS . 'widget' . DS . 'todo.tpl.html' ) , 'other' => intval($data['data']['other'])   ) ) , 'rest' );
			}
			else
				return render( array( 'code' => 100002 , 'message' => 'can not save data' ) , 'rest' );
			//return render( array( 'code' => 0 , 'data' => $data['data'] ) , 'rest' );
		}

		return render( array( 'code' => 100001 , 'message' => 'can not get api content' ) , 'rest' );

	}

	/**
	 * build data for ajax
	 *
	 * @return void
	 **/
	function todo_data()
	{
		$type = z(t(v('type')));

		$params = array();
		$params['by'] = 'tid';
		$params['ord'] = 'desc';
		$params['count'] = '100';
		$params['group'] = '1';
		
		
		if($content = send_request( 'todo_list' ,  $params , token()  ))
		{
			$data = json_decode($content , 1);
			if( intval($data['err_code']) != 0 ) 
				return false;
			else
			{
				if( $type == 'follow' ) $data['data'] =  $data['data']['follow'];
				elseif( $type == 'star' ) $data['data'] =  $data['data']['star'];
				elseif( $type == 'done' ) $data['data'] =  $data['data']['done'];
				else $data['data'] =  $data['data']['normal'];

			} 
				return render( $data , 'ajax' , 'raw'  );

		}

		return null;


		
				
	}
	
	

	function feed()
	{
		// aoi created it
	}
}