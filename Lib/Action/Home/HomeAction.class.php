<?php
/**
 * 前台展厅控制器基类
 *
 * @package Action
 * @subpackage Home
 * @stage 1.0
 * @author Terry <admin@huicms.cn>
 * @date 2013-05-13
 */
abstract class HomeAction extends Action{
    
    /**
     * 基类初始化操作
     * @author Terry <admin@52sum.com>
     * @date 2012-04-15
     */
    public function _initialize() {
        $this->_title();
        if(!C('site_status')){
            header('Content-Type:text/html; charset=utf-8');
            exit(C('site_close'));
        }
        import('ORG.Util.Page');
    }
    
    public function _title(){
        //SEO
        $config = D("Config")->getCfgByModule("WEBSITE");
        $page_seo = json_decode($config['WEBSITE'],true);
//        echo "<pre>";print_r($page_seo);exit;
        C($page_seo);
        $this->assign($page_seo);
    }
}