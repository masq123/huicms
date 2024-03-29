<?php

/**
 * 后台基类
 *
 * @subpackage Admin
 * @package Action
 * @stage 1.0
 * @author Terry<admin@huicms.cn>
 * @date 2013-3-25
 * @copyright Copyright (C) 2012, Shanghai Huicms Co., Ltd.
 */
abstract class AdminAction extends Action {

    protected $_name = '';

    /**
     * 顶部大栏目
     * @var array
     */
    private $tops = array();

    /**
     * 左侧各级菜单
     * @var array
     */
    private $menus = array();
    
    /**
     * 面包屑导航
     * @return array
     */
    private $breadcrumbs = array();

    /**
     * 基类初始化操作
     * @author Terry<admin@huicms.cn>
     * @date 2013-3-25
     */
    public function _initialize() {
        $this->doCheckLogin();
        $this->_name = $this->getActionName();
        $langSet = C('DEFAULT_LANG');
        //读取公共语言包
        L(include LANG_PATH . $langSet . '/Common.php');

        // 读取当前模块语言包
        if (is_file(LANG_PATH . $langSet . '/' . MODULE_NAME . '.php')) {
            L(include LANG_PATH . $langSet . '/' . MODULE_NAME . '.php');
        }
        //判断用户是否登陆
        
        $ary_get = $this->_get();
        $module = $ary_get['_URL_'][1] ? $ary_get['_URL_'][1] : "Index";
        $action = $ary_get['_URL_'][2] ? $ary_get['_URL_'][2] : "index";
        if(!empty($module) && !empty($action)){
            $array_where = array();
            $array_where['action'] = $action;
            $array_where['module'] = $module;
            $array_where['status'] = '1';
            $array_where['is_show'] = '1';
            $rolenode = D("RoleNode")->where($array_where)->order('sort asc')->find();
            
            if(!empty($rolenode) && is_array($rolenode)){
                $navid = $rolenode['nav_id'];
            }else{
                $node = D("RoleNode")->where(array('module'=>$module,'action'=>array('NEQ',''),'status'=>'1'))->order('sort asc')->find();
                $navid = $node['nav_id'];
                $module = $node['module'];
                $action = $node['action'];
            }
        }
        $this->assign("modulename",$module);
        $this->assign("actionname",$action);
        $this->assign("navid",$navid);
        $navname = D("RoleNav")->where(array('id' => $navid))->find();
        session("navname", $navname['name']);
        $rolenav = M('RoleNav')->field(C('DB_PREFIX') . 'role_nav.name,' . C('DB_PREFIX') . 'role_node.*')
                ->join(C('DB_PREFIX') . 'role_node ON ' . C('DB_PREFIX') . 'role_nav.id = ' . C('DB_PREFIX') . 'role_node.`nav_id`')
                ->where(C('DB_PREFIX') . 'role_nav.id =  "' . $navid . '" AND ' . C('DB_PREFIX') . 'role_node.`action` =  "' . $action . '" AND ' . C('DB_PREFIX') . 'role_node.`module` =  "' . $module . '"')
                ->find();
        if (!empty($rolenav) && is_array($rolenav)) {
            cookie("menuid", $rolenav['id']);
        }
        import('ORG.Util.Session');
        $this->assign("uid", session("admin"));
        $admin_access = D('Config')->getCfgByModule('ADMIN_ACCESS');
        if (intval($admin_access['EXPIRED_TIME']) > 0 && Session::isExpired()) {
            unset($_SESSION[C('USER_AUTH_KEY')]);
            unset($_SESSION);
            session_destroy();
        }
        if (intval($admin_access['EXPIRED_TIME']) > 0) {
            Session::setExpire(time() + $admin_access['EXPIRED_TIME'] * 60);
        }
        if (C('USER_AUTH_ON') && !in_array(MODULE_NAME, explode(',', C('NOT_AUTH_MODULE')))) {
            $rbac = new Arbac();
            if (!$rbac->AccessDecision()) {
                //检查认证识别号
                if (!$_SESSION [C('USER_AUTH_KEY')]) {
                    //跳转到认证网关
                    redirect(PHP_FILE . C('USER_AUTH_GATEWAY'));
                }
                // 没有权限 抛出错误
                if (C('RBAC_ERROR_PAGE')) {
                    // 定义权限错误页面
                    redirect(C('RBAC_ERROR_PAGE'));
                } else {
                    if (C('GUEST_AUTH_ON')) {
                        $this->assign('jumpUrl', PHP_FILE . C('USER_AUTH_GATEWAY'));
                    }
                    // 提示错误信息
                    $this->error(L('_VALID_ACCESS_'));
                }
            }
        }
        $this->getTop();
        $this->getMenus($navid);
        $this->_Breadcrumb($navid);
        import('ORG.Util.Page');
        import('ORG.Util.Tree');
        import('ORG.Util.Dir');
    }

    public function _Breadcrumb($navid){
        $module = MODULE_NAME;
        $action = ACTION_NAME;
        $array_where = array();
        $array_where['action'] = $action;
        $array_where['module'] = $module;
        $array_where['status'] = '1';
        $rolenav = M('RoleNav')->field(C('DB_PREFIX') . 'role_nav.name,' . C('DB_PREFIX') . 'role_node.*')
                ->join(C('DB_PREFIX') . 'role_node ON ' . C('DB_PREFIX') . 'role_nav.id = ' . C('DB_PREFIX') . 'role_node.`nav_id`')
                ->where(C('DB_PREFIX') . 'role_nav.id =  "' . $navid . '" AND ' . C('DB_PREFIX') . 'role_node.`action` =  "' . $action . '" AND ' . C('DB_PREFIX') . 'role_node.`module` =  "' . $module . '"')
                ->find();

        $this->assign('breadcrumbs', $rolenav);
        $this->breadcrumbs = $rolenav;
    }
    
    /**
     * 判断用户是否登陆
     * @author Terry<wanghui@huicms.cn>
     * @date 2013-3-25
     */
    public function doCheckLogin() {
        //todo 此处要做登录判断
        if (!session(C('USER_AUTH_KEY'))) {
            $int_port = "";
            if ($_SERVER["SERVER_PORT"] != 80) {
                $int_port = ':' . $_SERVER["SERVER_PORT"];
            }
            $string_request_uri = "http://" . $_SERVER["SERVER_NAME"] . $int_port . $_SERVER['REQUEST_URI'];
            $this->error(L('NO_LOGIN'), U('Admin/User/pageLogin') . '?doUrl=' . urlencode($string_request_uri));
        } else {
            $this->admin = session(C('USER_AUTH_KEY'));
        }
    }

    /**
     * 获取顶部导航信息
     * @author Terry<admin@huicms.cn>
     * @date 2013-04-03
     */
    public function getTop() {
        $tops = D('RoleNav')->where('status=1')->field('id,name')->order("sort ASC")->select();
        if(!empty($tops) && is_array($tops)){
            foreach ($tops as &$val){
                $where = array();
                $where['action'] = array('NEQ','');
                $where['nav_id'] = $val['id'];
                $where['is_show'] = '1';
                $where['status'] = '1';
                $where['auth_type'] = array('NEQ','1');
                $rolenode = D("RoleNode")->where($where)->order('sort asc')->find();
                $val['module'] = $rolenode['module'];
                $val['action'] = $rolenode['action'];
                $val['rn_id'] = $rolenode['id'];
                $val['nav_id'] = $rolenode['nav_id'];
            }
        }
        $this->tops = $tops;
        $this->assign('tops', $tops);
    }

    /**
     * 获取左侧菜单信息
     * @author Terry<admin@huicms.cn>
     * @date 2013-04-03
     */
    public function getMenus($menuid) {
        $menus = array();
        if (session(C("ADMIN_AUTH_KEY"))) {
            $id = intval($menuid);
            $where = array();
            $where['status'] = '1';
            $where['nav_id'] = $menuid;
            $where['is_show'] = '1';
            $where['auth_type'] = 0;
            $no_modules = explode(',', strtoupper(C('NOT_AUTH_MODULE')));
            $access_list = $_SESSION['_ACCESS_LIST'];
            $node_list = D("RoleNode")->where($where)->field('id,action,action_name,module,module_name,nav_id')->order(array('sort' => 'ASC'))->select();
            if (!empty($node_list) && is_array($node_list)) {
                foreach ($node_list as $key => $node) {
                    $menus[$node['module']]['nodes'][] = array_unique($node);
                    $menus[$node['module']]['name'] = $node['module_name'];
                    if ((isset($access_list[strtoupper($node['module'])]['MODULE']) || isset($access_list[strtoupper($node['module'])][strtoupper($node['action'])])) || $_SESSION['administrator'] || in_array(strtoupper($node['module']), $no_modules)) {
                        if (!in_array($node['id'], $menus[$node['module']]['nodes'][$key])) {
                            $menus[$node['module']]['nodes'][] = array_unique($node);
                        }
                        $menus[$node['module']]['name'] = $node['module_name'];
                    }
                }
            }
            $_SESSION['menu_' . $id . '_' . $_SESSION[C('USER_AUTH_KEY')]] = $menus;
        } else {
            $menus = $this->getOrdinaryPermissions($menuid);
        }
        $this->menus = $menus;
        $this->assign("menus", $menus);
        return $menus;
    }

    /**
     * 获取普通管理员的权限
     * @author Terry<admin@huicms.cn>
     * @date 2013-09-08
     */
    public function getOrdinaryPermissions($menuid) {
        //取出当前用户的权限
        $u_id = $_SESSION[C('USER_AUTH_KEY')];
        $where = array();
        $where[C('DB_PREFIX')."admin.u_id"] = $u_id;
        $where[C('DB_PREFIX')."role_node.is_show"] = "1";
        $arr_access_list = D("RoleNode")
                           ->field(array(C('DB_PREFIX')."role_node.id,".C('DB_PREFIX')."role_node.action,".C('DB_PREFIX')."role_node.action_name,".C('DB_PREFIX')."role_node.module,".C('DB_PREFIX')."role_node.module_name,".C('DB_PREFIX')."role_node.nav_id"))
                           ->join(" ".C('DB_PREFIX')."role_access on ".C('DB_PREFIX')."role_access.node_id=".C('DB_PREFIX')."role_node.id")
                           ->join(" ".C('DB_PREFIX')."admin on ".C('DB_PREFIX')."role_access.role_id=".C('DB_PREFIX')."admin.role_id")
                           ->where($where)
                           ->select();
        $data_menu = array();
        if(!empty($arr_access_list) && is_array($arr_access_list)){
            foreach($arr_access_list as $keymenu=>$valmenu){
                if(!empty($valmenu['action'])){
                    $data_menu[$valmenu['module']][$valmenu['action']] = $valmenu;
                }else{
                    $role_data = D("RoleNode")->where(array('is_show'=>'1','status'=>'1','module'=>$valmenu['module'],'action'=>array('NEQ','')))->select();
                    if(!empty($role_data) && is_array($role_data)){
                        foreach($role_data as $keyrl=>$valrl){
                            $data_menu[$valmenu['module']][$valrl['action']] = $valrl;
                        }
                    }
                }
                
            }
        }
        
        $id = intval($menuid);
        $menus = array();
        $where = array();
        $where['status'] = '1';
        $where['nav_id'] = $menuid;
        $where['is_show'] = '1';
        $where['auth_type'] = 0;
        $no_modules = explode(',', strtoupper(C('NOT_AUTH_MODULE')));
        $access_list = $_SESSION['_ACCESS_LIST'];
        $node_list = D("RoleNode")->where($where)->field('id,action,action_name,module,module_name,nav_id')->order(array('sort' => 'ASC'))->select();
        if(!empty($node_list) && is_array($node_list)){
            foreach($node_list as $keydata=>$valdata){
                if($data_menu[$valdata['module']][$valdata['action']]['action'] != $valdata['action']){
                    unset($node_list[$keydata]);
                }else{
                    $menus[$valdata['module']]['nodes'][$valdata['action']] = array_unique($valdata);
                    $menus[$valdata['module']]['name'] = $valdata['module_name'];
                    if ((isset($access_list[strtoupper($valdata['module'])]['MODULE']) || isset($access_list[strtoupper($valdata['module'])][strtoupper($valdata['action'])])) || $_SESSION['administrator'] || in_array(strtoupper($valdata['module']), $no_modules)) {
                        if (!in_array($valdata['id'], $menus[$valdata['module']]['nodes'][$key])) {
                            $menus[$valdata['module']]['nodes'][$valdata['action']] = array_unique($valdata);
                        }
                        $menus[$valdata['module']]['name'] = $valdata['module_name'];
                    }
                }
                $_SESSION['menu_' . $id . '_' . $_SESSION[C('USER_AUTH_KEY')]] = $menus;
            }
        }
        return $menus;
    }

    /**
     * 通用删除操作
     * @author Terry<admin@huicms.cn>
     * @date 2013-05-16
     */
    public function doDelete() {
        $mod = D($this->_name);
        $pk = $mod->getPk();
        $ids = trim($this->_request($pk), ',');
        if ($ids) {
            if (false !== $mod->delete($ids)) {
                $this->success("删除成功");
            } else {
                $this->error("删除失败");
            }
        } else {
            $this->error("请选择删除的对象");
        }
    }

    /**
     * 后台统一分页
     * @author Terry<admin@huicms.cn>
     * @date 2013-05-31
     */
    public function _Page($count, $pagesize) {
        $page = new Page($count, $pagesize);
        $page->setConfig("header", "条");
        $page->setConfig('theme', '<li class="pageSelect">共%totalRow%%header%&nbsp;%nowPage%/%totalPage%页&nbsp;%first%&nbsp;%upPage%&nbsp;%prePage%&nbsp;%linkPage%&nbsp;%nextPage%&nbsp;%downPage%&nbsp;%end%</li>');
        return $page;
    }

}