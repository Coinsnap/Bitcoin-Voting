<?php
if (!defined('ABSPATH')){ exit; }
class Coinsnap_Bitcoin_Voting_Client {

    public function remoteRequest(string $method,string $url,array $headers = [],string $body = ''){
    
        $wpRemoteArgs = ['body' => $body, 'method' => $method, 'timeout' => 5, 'headers' => $headers];
        $response = wp_remote_request($url,$wpRemoteArgs);

        if(is_wp_error($response) ) {
            $errorMessage = $response->get_error_message();
            $errorCode = $response->get_error_code();
            return array('error' => ['code' => (int)esc_html($errorCode), 'message' => esc_html($errorMessage)]);
        }
        elseif(is_array($response)) {
            $status = $response['response']['code'];
            $responseHeaders = wp_remote_retrieve_headers($response)->getAll();
            $responseBody = json_decode($response['body'],true);
            return array('status' => $status, 'body' => $responseBody, 'headers' => $responseHeaders);
        }
    }
    
    public function getCurrencies(): array {
        if(defined('COINSNAP_CURRENCIES')){
            return COINSNAP_CURRENCIES;
        }
        else {
            return array("EUR","USD","SATS","BTC","CAD","JPY","GBP","CHF","RUB");
        }
    }
    
    const RATES_CACHE = 'coinsnap_bitcoin_voting_rates';
    const RATES_LAST_GOOD = 'coinsnap_bitcoin_voting_rates_last_good';
    const RATES_TTL = 10 * MINUTE_IN_SECONDS;
    const RATES_RETRY = 2 * MINUTE_IN_SECONDS;
    const RATES_MAX_AGE = DAY_IN_SECONDS;

    /*  loadExchangeRates() loads exchange rates for fiat and crypto currencies
     *  from coingecko.com. We don't send any data from the plugin or WordPress database.
     *  The rates are cached for a few minutes, so one page view or vote doesn't call
     *  CoinGecko again; if CoinGecko fails (e.g. 429 Too Many Requests), the last rates
     *  that worked are used for up to a day.
     *  Method returns array with result code, exchange rates or error
     */
    public function loadExchangeRates(): array {
        $cached = get_transient(self::RATES_CACHE);
        if (is_array($cached) && isset($cached['rates']) && is_array($cached['rates'])) {
            return array('result' => true, 'data' => $cached['rates']);
        }

        $url = 'https://api.coingecko.com/api/v3/exchange_rates';
        $response = $this->remoteRequest('GET', $url, []);
        $body = $response['body'] ?? null;
        $error = '';
        if (($response['status'] ?? 0) !== 200) {
            $error = 'ratesLoadingError';
        }
        elseif (!is_array($body) || !isset($body['rates']) || !is_array($body['rates']) || count($body['rates']) < 1) {
            $error = 'ratesListError';
        }

        if ($error === '') {
            set_transient(self::RATES_CACHE, ['rates' => $body['rates']], self::RATES_TTL);
            update_option(self::RATES_LAST_GOOD, ['rates' => $body['rates'], 'time' => time()], false);
            return array('result' => true, 'data' => $body['rates']);
        }

        $last = get_option(self::RATES_LAST_GOOD, []);
        if (is_array($last) && isset($last['rates']) && is_array($last['rates']) && time() - (int) ($last['time'] ?? 0) <= self::RATES_MAX_AGE) {
            // Ask CoinGecko again in a short while, not on every request.
            set_transient(self::RATES_CACHE, ['rates' => $last['rates']], self::RATES_RETRY);
            return array('result' => true, 'data' => $last['rates']);
        }
        return array('result' => false, 'error' => $error);
    }
    
    public function checkPaymentData($amount,$currency,$provider = 'coinsnap',$mode = 'invoice'): array {
        
        $btcPayCurrencies = $this->loadExchangeRates();
            
        if(!$btcPayCurrencies['result']){
                return array('result' => false,'error' => $btcPayCurrencies['error'],'min_value' => '');
            }
            
            elseif(!isset($btcPayCurrencies['data'][strtolower($currency)]) || $btcPayCurrencies['data'][strtolower($currency)]['value'] <= 0){
                return array('result' => false,'error' => 'currencyError','min_value' => '');
            }
            
            $rate = 1/$btcPayCurrencies['data'][strtolower($currency)]['value'];
                
        
        if($provider === 'bitcoin' || $provider === 'lightning'){
            
            
            
                $eurbtc = (isset($btcPayCurrencies['data']['eur']['value']))? 1/$btcPayCurrencies['data']['eur']['value']*0.50 : 0.000005;
                $min_value_btcpay = ($provider === 'bitcoin')? $eurbtc : 0.0000001;
                $min_value = $min_value_btcpay/$rate;
                
               if($mode === 'calculation'){
                    return array('result' => true, 'min_value' => round($min_value,2),'rate' => $rate);
                }
                
                else {                
                    if(round($amount * $rate * 1000000) < round($min_value_btcpay * 1000000)){
                        return array('result' => false,'error' => 'amountError','min_value' => round($min_value,2));
                    }
                    else {
                        return array('result' => true,'rate' => $rate);
                    }
                }
        }
        
        if($provider === 'coinsnap' || $provider === 'lightning'){
        
            $coinsnapCurrencies = $this->getCurrencies();

            if(!is_array($coinsnapCurrencies)){
                return array('result' => false,'error' => 'currenciesError','min_value' => '');
            }
            if(!in_array($currency,$coinsnapCurrencies)){
                return array('result' => false,'error' => 'currencyError','min_value' => '');
            }
            
            $min_value_array = ["SATS" => 1,"JPY" => 1,"RUB" => 1,"BTC" => 0.000001];
            $min_value = (isset($min_value_array[$currency]))? $min_value_array[$currency] : 0.01;
            
            if($mode === 'calculation'){
                return array('result' => true,'min_value' => $min_value);
            }
            
            else {
                if($amount === null || $amount === 0){
                    return array('result' => false,'error' => 'amountError');
                }
                elseif($amount < $min_value){
                    return array('result' => false,'error' => 'amountError','min_value' => $min_value);
                }
                else {
                    return array('result' => true,'rate' => $rate);
                }
            }            
        }
    }
    
    public function getStore($storeURL,$apiKey,$storeId){
        
        $url = $storeURL.COINSNAP_API_PATH.COINSNAP_SERVER_PATH.'/' . urlencode($storeId);
        $headers = ['Accept' => 'application/json','Content-Type' => 'application/json','x-api-key' => $apiKey,'Authorization' => 'token '.$apiKey];
        $method = 'GET';
        $response = $this->remoteRequest($method, $url, $headers);
        
        if(isset($response['error'])){
            $result = array('error' => $response['error']);
            return $result;
        }
        
        if (isset($response['status']) && $response['status'] === 200){
            return array('code' => $response['status'], 'result' => $response['body']);
        }
        else {
            return ['code' => $response['status'] ?? 0, 'result' => []];
        }
    }
    
    /**
     * For BTCPay server only
     * @return [int $code, array $result]
     */
    public function getStorePaymentMethods($storeURL,$apiKey,$storeId){
        
        $url = rtrim((string) $storeURL, '/').COINSNAP_API_PATH.COINSNAP_SERVER_PATH.'/' . rawurlencode((string) $storeId) . '/payment-methods';
        $headers = ['Accept' => 'application/json','Content-Type' => 'application/json','x-api-key' => $apiKey,'Authorization' => 'token '.$apiKey];
        $method = 'GET';
        $response = $this->remoteRequest($method, $url, $headers);
        
        if(isset($response['error'])){
            $result = array('error' => $response['error']);
            return $result;
        }
        
        if (isset($response['status']) && $response['status'] === 200){

            $json_decode = $response['body'];

            $result = array('response' => $json_decode);
                if(is_array($json_decode) && count($json_decode) > 0){
                    $result['onchain'] = false;
                    $result['lightning'] = false;
                    $result['usdt'] = false;
                    foreach($json_decode as $storePaymentMethod){
                        
                        if($storePaymentMethod['enabled'] > 0 && stripos($storePaymentMethod['paymentMethodId'],'BTC') !== false){
                            $result['onchain'] = true;
                        }
                        if($storePaymentMethod['enabled'] > 0 && ($storePaymentMethod['paymentMethodId'] === 'Lightning' || stripos($storePaymentMethod['paymentMethodId'],'-LN') !== false)) {
                            $result['lightning'] = true;
                        }
                        if($storePaymentMethod['enabled'] > 0 && stripos($storePaymentMethod['paymentMethodId'],'USDT') !== false){
                            $result['usdt'] = true;
                        }
                        
                    }
                }
            return array('code' => $response['status'], 'result' => $result);
        }
        else {
            return ['code' => $response['status'] ?? 0, 'result' => []];
        }
    }
}

