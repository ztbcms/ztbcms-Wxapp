<?php
/**
 * author: zhlhuang <zhlhuang888@foxmail.com>
 */

namespace Wxapp\Lib;

use Wxapp\Service\CappinfoService;
use Wxapp\Service\CsessioninfoService;
use Wxapp\Service\UserinfoService;


class Auth {

    /**
     *
     * 描述：登录校验，返回id和skey
     *
     * @param        $appid
     * @param        $code
     * @param        $encrypt_data
     * @param string $iv
     * @return mixed
     */
    public function get_id_skey($appid, $code, $encrypt_data, $iv = "old") {
        $cappinfo_data = CappinfoService::getAppInfo($appid)['data'];
        if (empty($cappinfo_data) || ($cappinfo_data == false)) {
            $ret['returnCode'] = ReturnCode::MA_NO_APPID;
            $ret['returnMessage'] = 'NO_APPID';
            $ret['returnData'] = '';
        } else {
            $appid = $cappinfo_data['appid'];
            $secret = $cappinfo_data['secret'];
            $login_duration = $cappinfo_data['login_duration'];
            $url = 'https://api.weixin.qq.com/sns/jscode2session?appid=' . $appid . '&secret=' . $secret . '&js_code=' . $code . '&grant_type=authorization_code';
            $http_util = new HttpUtil();
            $return_message = $http_util->http_get($url);

            if ($return_message != false) {
                $json_message = json_decode($return_message, true);
                if (isset($json_message['openid']) && isset($json_message['session_key'])) {
                    $uuid = md5((time() - mt_rand(1, 10000)) . mt_rand(1, 1000000));//生成UUID
                    $skey = md5(time() . mt_rand(1, 1000000));//生成skey
                    $create_time = date('Y-m-d H:i:s', time());
                    $last_visit_time = date('Y-m-d H:i:s', time());
                    $openid = $json_message['openid'];
                    $session_key = $json_message['session_key'];
                    $user_info = false;
                    $pc = new WXBizDataCrypt($appid, $session_key);
                    $errCode = $pc->decryptData($encrypt_data, $iv, $user_info);
                    $user_info = base64_encode($user_info);
                    if ($user_info === false || $errCode !== 0) {
                        $ret['returnCode'] = ReturnCode::MA_DECRYPT_ERR;
                        $ret['returnMessage'] = 'DECRYPT_FAIL';
                        $ret['returnData'] = '';
                    } else {
                        $params = array(
                            "uuid" => $uuid,
                            "skey" => $skey,
                            "create_time" => $create_time,
                            "last_visit_time" => $last_visit_time,
                            "openid" => $openid,
                            "session_key" => $session_key,
                            "user_info" => $user_info,
                            "login_duration" => $login_duration,
                            "appid" => $appid,
                        );

                        $csessioninfo_service = new CsessioninfoService();
                        $change_result = $csessioninfo_service->change_csessioninfo($params);
                        $user_info_arr = json_decode(base64_decode($user_info), true);
                        if ($change_result) {
                            $id = $csessioninfo_service->get_id_csessioninfo($openid);
                            $arr_result['id'] = $id;
                            $arr_result['skey'] = $skey;
                            $arr_result['user_info'] = $user_info_arr;
                            $arr_result['duration'] = $json_message['expires_in'];
                            $ret['returnCode'] = ReturnCode::MA_OK;
                            $ret['returnMessage'] = 'NEW_SESSION_SUCCESS';
                            $ret['returnData'] = $arr_result;
                        } else {
                            if ($change_result === false) {
                                $ret['returnCode'] = ReturnCode::MA_CHANGE_SESSION_ERR;
                                $ret['returnMessage'] = 'CHANGE_SESSION_ERR';
                                $ret['returnData'] = '';
                            } else {
                                $arr_result['id'] = $change_result;
                                $arr_result['skey'] = $skey;
                                $arr_result['user_info'] = $user_info_arr;
                                $arr_result['duration'] = $json_message['expires_in'];
                                $ret['returnCode'] = ReturnCode::MA_OK;
                                $ret['returnMessage'] = 'UPDATE_SESSION_SUCCESS';
                                $ret['returnData'] = $arr_result;
                            }
                        }
                    }
                } else {
                    if (isset($json_message['errcode']) && isset($json_message['errmsg'])) {
                        $ret['returnCode'] = ReturnCode::MA_WEIXIN_CODE_ERR;
                        $ret['returnMessage'] = 'WEIXIN_CODE_ERR';
                        $ret['returnData'] = '';
                    } else {
                        $ret['returnCode'] = ReturnCode::MA_WEIXIN_RETURN_ERR;
                        $ret['returnMessage'] = 'WEIXIN_RETURN_ERR';
                        $ret['returnData'] = '';
                    }
                }
            } else {
                $ret['returnCode'] = ReturnCode::MA_WEIXIN_NET_ERR;
                $ret['returnMessage'] = 'WEIXIN_NET_ERR';
                $ret['returnData'] = '';
            }
        }
        UserinfoService::updateInfo($user_info_arr, $appid);

        return $ret;
    }

    /**
     * @param $appid
     * @param $id
     * @param $skey
     * @return mixed
     */
    public function auth($appid, $id, $skey) {
        //根据Id和skey 在cSessionInfo中进行鉴权，返回鉴权失败和密钥过期
        $cappinfo_data = CappinfoService::getAppInfo($appid)['data'];
        if (empty($cappinfo_data) || ($cappinfo_data == false)) {
            $ret['returnCode'] = ReturnCode::MA_NO_APPID;
            $ret['returnMessage'] = 'NO_APPID';
            $ret['returnData'] = '';
        } else {
            $login_duration = $cappinfo_data['login_duration'];
            $session_duration = $cappinfo_data['session_duration'];
            $params = array(
                "uuid" => $id,
                "skey" => $skey,
                "login_duration" => $login_duration,
                "session_duration" => $session_duration
            );

            $csessioninfo_service = new CsessioninfoService();
            $auth_result = $csessioninfo_service->check_session_for_auth($params);
            if ($auth_result !== false) {
                $arr_result['user_info'] = json_decode(base64_decode($auth_result), true);
                $ret['returnCode'] = ReturnCode::MA_OK;
                $ret['returnMessage'] = 'AUTH_SUCCESS';
                $ret['returnData'] = $arr_result;
            } else {
                $ret['returnCode'] = ReturnCode::MA_AUTH_ERR;
                $ret['returnMessage'] = 'AUTH_FAIL';
                $ret['returnData'] = '';
            }
        }

        return $ret;
    }
}