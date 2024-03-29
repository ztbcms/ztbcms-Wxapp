<?php
/**
 * author: zhlhuang <zhlhuang888@foxmail.com>
 */
namespace Wxapp\Lib;

class DecryptData {

    /**
     * @param $encrypt_data
     * @param $session_key
     * @return bool|string
     * 描述：解密数据
     */
    public function aes128cbc_Decrypt($encrypt_data, $session_key) {
        $aeskey = base64_decode($session_key);
        $iv = $aeskey;
        $encryptedData = base64_decode($encrypt_data);
        $decrypted = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $aeskey, $encryptedData, MCRYPT_MODE_CBC, $iv);

        return $this->stripPkcs7Padding($decrypted);
    }

    /**
     * 对解密后的明文进行补位删除
     *
     * @param string 解密后的明文
     * @return string 删除填充补位后的明文
     */
    function stripPkcs7Padding($text) {

        $pad = ord(substr($text, -1));
        if ($pad < 1 || $pad > 32) {
            $pad = 0;
        }

        return substr($text, 0, (strlen($text) - $pad));
    }
}