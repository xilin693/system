<?php

namespace king\lib;

use king\core\Instance;

class Rsa extends Instance
{
    private $public_key;
    private $private_key;
    private $open_ssl_path;

    public function __construct($public_key_file = '', $private_key_file = '', $open_ssl_path = '')
    {
        if ($public_key_file) {
            $this->getPublicKey($public_key_file);
        }
        if ($private_key_file) {
            $this->getPrivateKey($private_key_file);
        }
        if ($open_ssl_path) {
            $this->open_ssl_path = $open_ssl_path;
        }
    }

    private function error($msg)
    {
        die('RSA Error:' . $msg); //TODO
    }

    public function sign($data, $code = 'base64')
    {
        $ret = false;
        if (openssl_sign($data, $ret, $this->private_key)) {
            $ret = $this->encode($ret, $code);
        }
        return $ret;
    }

    public function verify($data, $sign, $code = 'base64')
    {
        $ret = false;
        $sign = $this->decode($sign, $code);
        if ($sign !== false) {
            switch (openssl_verify($data, $sign, $this->public_key)) {
                case 1:
                    $ret = true;
                    break;
                case 0:
                case -1:
                default:
                    $ret = false;
            }
        }
        return $ret;
    }

    public function privateEncrypt($data, $code = 'base64', $padding = OPENSSL_PKCS1_PADDING)
    {
        $ret = false;
        if (!$this->checkPadding($padding, 'en')) $this->error('padding error');
        if (openssl_private_encrypt($data, $result, $this->private_key, $padding)) {
            $ret = $this->encode($result, $code);
        }
        return $ret;
    }

    public function encrypt($data, $code = 'base64', $padding = OPENSSL_PKCS1_PADDING)
    {
        $ret = false;
        if (!$this->checkPadding($padding, 'en')) $this->error('padding error');
        if (openssl_public_encrypt($data, $result, $this->public_key, $padding)) {
            $ret = $this->encode($result, $code);
        }
        return $ret;
    }

    public function decrypt($data, $code = 'base64', $padding = OPENSSL_PKCS1_PADDING, $rev = false)
    {
        $ret = false;
        $data = $this->decode($data, $code);
        if (!$this->checkPadding($padding, 'de')) $this->error('padding error');
        if ($data !== false) {
            if (openssl_private_decrypt($data, $result, $this->private_key, $padding)) {
                $ret = $rev ? rtrim(strrev($result), "\0") : '' . $result;
            }
        }
        return $ret;
    }

    public function publicDecrypt($data, $code = 'base64', $padding = OPENSSL_PKCS1_PADDING, $rev = false)
    {
        $ret = false;
        $data = $this->decode($data, $code);
        if (!$this->checkPadding($padding, 'de')) $this->error('padding error');
        if ($data !== false) {
            if (openssl_public_decrypt($data, $result, $this->public_key, $padding)) {
                $ret = $rev ? rtrim(strrev($result), "\0") : '' . $result;
            }
        }
        return $ret;
    }

    public function buildNewKey()
    {
        $config = [
            'private_key_bits' => 2048,
        ];
        $resource = openssl_pkey_new($config);
        openssl_pkey_export($resource, $privateKey);
        if (!$resource) {
            $config['config'] = $this->open_ssl_path;
            $resource = openssl_pkey_new($config);
            openssl_pkey_export($resource, $privateKey, null, $config);
        }
        $detail = openssl_pkey_get_details($resource);
        $publicKey = $detail['key'];
        echo "<pre>";
        echo "$publicKey";

        echo "$privateKey";
        echo "</pre>";

    }

    private function checkPadding($padding, $type)
    {
        if ($type == 'en') {
            switch ($padding) {
                case OPENSSL_PKCS1_PADDING:
                    $ret = true;
                    break;
                default:
                    $ret = false;
            }
        } else {
            switch ($padding) {
                case OPENSSL_PKCS1_PADDING:
                case OPENSSL_NO_PADDING:
                    $ret = true;
                    break;
                default:
                    $ret = false;
            }
        }
        return $ret;
    }

    private function encode($data, $code)
    {
        switch (strtolower($code)) {
            case 'base64':
                $data = base64_encode('' . $data);
                break;
            case 'hex':
                $data = bin2hex($data);
                break;
            case 'bin':
            default:
        }
        return $data;
    }

    private function decode($data, $code)
    {
        switch (strtolower($code)) {
            case 'base64':
                $data = base64_decode($data);
                break;
            case 'hex':
                $data = $this->hex2bin($data);
                break;
            case 'bin':
            default:
        }
        return $data;
    }

    private function getPublicKey($file)
    {
        if (is_file($file)) {
            $key_content = file_get_contents($file);
        } else {
            $key_content = $file;
        }

        $this->public_key = openssl_get_publickey($key_content);
    }

    private function getPrivateKey($file)
    {
        if (is_file($file)) {
            $key_content = file_get_contents($file);
        } else {
            $key_content = $file;
        }

        $this->private_key = openssl_get_privatekey($key_content);
    }

    private function hex2bin($hex = false)
    {
        $ret = $hex !== false && preg_match('/^[0-9a-fA-F]+$/i', $hex) ? pack("H*", $hex) : false;
        return $ret;
    }
}