<?php

/**
 * Copyright 2016 LINE Corporation
 *
 * LINE Corporation licenses this file to you under the Apache License,
 * version 2.0 (the "License"); you may not use this file except in compliance
 * with the License. You may obtain a copy of the License at:
 *
 *   https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

namespace LINE\LINEBot\HTTPClient;

use LINE\LINEBot\Constant\Meta;
use LINE\LINEBot\Exception\CurlExecutionException;
use LINE\LINEBot\HTTPClient;
use LINE\LINEBot\Response;

/**
 * Class CurlHTTPClient.
 *
 * A HTTPClient that uses cURL.
 *
 * @package LINE\LINEBot\HTTPClient
 */
class CurlHTTPClient implements HTTPClient
{
    /** @var array */
    private $authHeaders;
    /** @var array */
    private $userAgentHeader;

    /**
     * CurlHTTPClient constructor.
     *
     * @param string $channelToken Access token of your channel.
     */
    public function __construct($channelToken)
    {
        $this->authHeaders = [
            "Authorization: Bearer $channelToken",
        ];
        $this->userAgentHeader = [
            'User-Agent: LINE-BotSDK-PHP/' . Meta::VERSION,
        ];
    }

    /**
     * Sends GET request to LINE Messaging API.
     *
     * @param string $url Request URL.
     * @param array $data Request body
     * @param array $headers Request headers.
     * @return Response Response of API request.
     * @throws CurlExecutionException
     */
    public function get($url, array $data = [], array $headers = [])
    {
        if ($data) {
            $url .= '?' . http_build_query($data);
        }
        return $this->sendRequest('GET', $url, $headers);
    }

    /**
     * Sends POST request to LINE Messaging API.
     *
     * @param string $url Request URL.
     * @param array $data Request body or resource path.
     * @param array|null $headers Request headers.
     * @return Response Response of API request.
     * @throws CurlExecutionException
     */
    public function post($url, array $data, array $headers = null)
    {
        $headers = is_null($headers) ? ['Content-Type: application/json; charset=utf-8'] : $headers;
        $options = [];
        if (is_null($data)) {
            // Rel: https://github.com/line/line-bot-sdk-php/issues/35
            $options[CURLOPT_HTTPHEADER][] = 'Content-Length: 0';
        } else {
            if (isset($data['__file']) && isset($data['__type'])) {
                $options[CURLOPT_PUT] = true;
                $options[CURLOPT_INFILE] = fopen($data['__file'], 'r');
                $options[CURLOPT_INFILESIZE] = filesize($data['__file']);
            } elseif (in_array('Content-Type: application/x-www-form-urlencoded', $headers)) {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = http_build_query($data);
            } elseif (!empty($data)) {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
            } else {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = $data;
            }
        }
        return $this->sendRequest('POST', $url, $headers, $options);
    }

    /**
     * Sends DELETE request to LINE Messaging API.
     *
     * @param string $url Request URL.
     * @return Response Response of API request.
     * @throws CurlExecutionException
     */
    public function delete($url)
    {
        return $this->sendRequest('DELETE', $url, [], []);
    }

    /**
     * @param string $method
     * @param string $url
     * @param array $additionalHeader
     * @param string|null $reqBody
     * @return Response
     * @throws CurlExecutionException
     */
    private function sendRequest($method, $url, array $additionalHeader, $options = [])
    {
        $curl = new Curl($url);

        $headers = array_merge($this->authHeaders, $this->userAgentHeader, $additionalHeader);

        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_HEADER => true,
        ] + $options;
        $curl->setoptArray($options);

        $result = $curl->exec();

        if ($curl->errno()) {
            throw new CurlExecutionException($curl->error());
        }

        $info = $curl->getinfo();
        $httpStatus = $info['http_code'];

        $responseHeaderSize = $info['header_size'];

        $responseHeaderStr = substr($result, 0, $responseHeaderSize);
        $responseHeaders = [];
        foreach (explode("\r\n", $responseHeaderStr) as $responseHeader) {
            $kv = explode(':', $responseHeader, 2);
            if (count($kv) === 2) {
                $responseHeaders[$kv[0]] = trim($kv[1]);
            }
        }

        $body = substr($result, $responseHeaderSize);

        if (isset($options[CURLOPT_INFILE])) {
            fclose($options[CURLOPT_INFILE]);
        }

        return new Response($httpStatus, $body, $responseHeaders);
    }
}
