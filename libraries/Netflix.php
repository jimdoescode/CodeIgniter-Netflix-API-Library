<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
Copyright (C) 2011 by Jim Saunders

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/

class Netflix
{
    const SCHEME        = 'http';
    const HOST          = 'api.netflix.com';
    const AUTHORIZE_URI = 'api-user.netflix.com/oauth/login';
    const REQUEST_URI   = '/oauth/request_token';
    const ACCESS_URI    = '/oauth/access_token';
    
    const HTTP_1        = '1.1';
    const LINE_END      = "\r\n";
    
    const DEBUG = false;
    
    //Array that should contain the consumer secret and
    //key which should be passed into the constructor.
    private $_consumer = array();
    private $_access = array();
    
    private $_header = array(
        'Host'=>self::HOST,
        'Connection'=>'close',
        'User-Agent'=>'CodeIgniter',
        'Accept-encoding'=>'identity'
    );
    
    /**
     * Pass in a parameters array which should look as follows:
     * array('key'=>'example.com', 'secret'=>'mysecret');
     * Note that the secret should either be a hash string for
     * HMAC signatures or a file path string for RSA signatures.
     *
     * @param array $params
     */
    public function netflix($params)
    {
        $this->CI =& get_instance();
        $this->CI->load->helper('oauth');
        $this->CI->load->helper('string');
        
        if(!array_key_exists('method', $params))$params['method'] = 'GET';
        $params['algorithm'] = OAUTH_ALGORITHMS::HMAC_SHA1; //Only thing available in netflix
        
        $this->_consumer = array_diff_key($params, array('access'=>0));
        if(array_key_exists('access', $params))$this->_access = $params['access'];
    }
    
    /**
     * Sets OAuth access data to authenticate a user with dropbox.
     *
     * @param array $access an array of the form
     *                      array('oauth_token'=>url encoded token,'oauth_token_secret'=>url encoded secret)
     **/
    public function set_oauth_access(array $access)
    {
        $this->_access = $access;
    }
    
    /**
     * This is called to begin the oauth token exchange. This should only
     * need to be called once for a user, provided they allow oauth access.
     * It will return a URL that your site should redirect to, allowing the
     * user to login and accept your application.
     *
     * @param string $callback the page on your site you wish to return to
     *                         after the user grants your application access.
     * @return mixed either the URL to redirect to, or if they specified HMAC
     *         signing an array with the token_secret and the redirect url
     */
    public function get_request_token($callback)
    {
        $baseurl = self::SCHEME.'://'.self::HOST.self::REQUEST_URI;

        //Generate an array with the initial oauth values we need
        $auth = build_auth_array($baseurl, $this->_consumer['key'], $this->_consumer['secret'],
                                 array(),
                                 $this->_consumer['method'], $this->_consumer['algorithm']);
        //Create the "Authorization" portion of the header
        $str = '';
        foreach($auth as $key => $value)
            $str .= ",{$key}=\"{$value}\"";
        $str = 'Authorization: OAuth '.substr($str, 1);
        //Send it
        $response = $this->_connect($baseurl, $str, 'GET');
        
        if(self::DEBUG)error_log($response);
        
        //We should get back a request token and secret which
        //we will add to the redirect url.
        parse_str($response, $resarray);
        
        $resarray['application_name'] = urlencode($resarray['application_name']);
        
        //Return the full redirect url and let the user decide what to do from there
        $redirect = "https://".self::AUTHORIZE_URI."?oauth_token={$resarray['oauth_token']}&oauth_consumer_key={$this->_consumer['key']}&application_name={$resarray['application_name']}&oauth_callback={$callback}";
        
        return array('token_secret'=>$resarray['oauth_token_secret'], 'redirect'=>$redirect);
    }
    
    public function get_access_token($secret = false, $token = false, $verifier = false)
    {
        //If no request token was specified then attempt to get one from the url
        if($token === false && isset($_GET['oauth_token']))$token = $_GET['oauth_token'];
        if($verifier === false && isset($_GET['oauth_verifier']))$verifier = $_GET['oauth_verifier'];
        //If all else fails attempt to get it from the request uri.
        if($token === false && $verifier === false)
        {
            $uri = $_SERVER['REQUEST_URI'];
            $uriparts = explode('?', $uri);

            $authfields = array();
            parse_str($uriparts[1], $authfields);
            $token = $authfields['oauth_token'];
            $verifier = $authfields['oauth_verifier'];
        }
        
        $tokenddata = array('oauth_token'=>urlencode($token), 'oauth_token_secret'=>urlencode($secret));
        
        $baseurl = self::SCHEME.'://'.self::HOST.self::ACCESS_URI;
        //Include the token and verifier into the header request.
        $auth = get_auth_header($baseurl, $this->_consumer['key'], $this->_consumer['secret'],
                                $tokenddata, $this->_consumer['method'], $this->_consumer['algorithm']);

        $response = $this->_connect($baseurl, $auth, 'GET');
        
        //Parse the response into an array it should contain
        //both the access token and the secret key. (You only
        //need the secret key if you use HMAC-SHA1 signatures.)
        parse_str($response, $oauth);
        
        $this->_access = $oauth;
        
        //Return the token and secret for storage
        return $oauth;
    }
    
    /**
     * Specify your own GET request to the NetFlix API.
     *
     * @param string $uri The uri to retrieve data from.
     **/
    public function raw_request($uri)
    {
        return $this->_response_request($uri);
    }
    
    /**
     * Search for a title
     *
     * @param string $title the name of the title to search for
     * @param array $params (optional) Additional parameters. See the netflix API reference for details
     **/
    public function search_title($title, array $params = array())
    {
        $title = rawurlencode($title);
        $parstr = empty($params) ? '' : '&'.http_build_query($params);
        return $this->_response_request("catalog/titles?term={$title}{$parstr}");
    }
    
    /**
     * Search for autocomplete results for a title
     *
     * @param string $title the name of the title to search for
     **/
    public function search_title_autocomplete($title)
    {
        $title = rawurlencode($title);
        return $this->_response_request("catalog/titles/autocomplete?term={$title}");
    }
    
    /**
     * Retrieve a complete index of all instant-watch titles in the Netflix catalog
     *
     * @param array $params (optional) Additional parameters. See the netflix API reference for details
     **/
    public function all_titles(array $params = array())
    {
        $parstr = empty($params) ? '' : '?'.http_build_query($params);
        //Request that the response be gzipped because it will be massive
        $this->_header['Accept-encoding'] = 'gzip';
        $response = $this->_response_request("catalog/titles/index{$parstr}");
        //Reset the encoding for normal use.
        $this->_header['Accept-encoding'] = 'identity';
        return $response;
    }
    
    /**
     * Retrieve details for specific instant-watch title
     *
     * @param string $id The id of the title to search for.
     * @param string $type The type of the title (either 'movies', 'series', or 'programs')
     * @param mixed $season (optional) Specify a particular season number of a series or false.
     **/
    public function get_title_details($id, $type, $season = false)
    {
        $seasonstr = $season === false ? '' : "/season/{$season}";
        return $this->_response_request("catalog/titles/{$type}/{$id}{$seasonstr}");
    }
    
    /**
     * Retrieve a list of movie titles similar to a particular title.
     *
     * @param string $id The id of the title to search for.
     * @param string $type The type of the title (either 'movies', 'series', or 'programs')
     * @param mixed $season (optional) Specify a particular season number of a series or false.
     * @param array $params (optional) Additional parameters. See the netflix API reference for details
     **/
    public function get_title_similars($id, $type, $season = false, array $params = array())
    {
        $seasonstr = $season === false ? '' : "/season/{$season}";
        $parstr = empty($params) ? '' : '?'.http_build_query($params);
        return $this->_response_request("catalog/titles/{$type}/{$id}/similars{$seasonstr}{$parstr}");
    }
    
    /**
     * Search for people in the catalog by their name or a portion of their name.
     *
     * @param string $name The full or partial name to search for.
     * @param array $params (optional) Additional parameters. See the netflix API reference for details
     **/
    public function search_people($name, array $params = array())
    {
        $name = rawurlencode($name);
        $parstr = empty($params) ? '' : '&'.http_build_query($params);
        return $this->_response_request("catalog/people?term={$name}{$parstr}");
    }
    
    /**
     * Retrieve detailed information about a person in the Catalog
     *
     * @param string $id The id of the person to search for
     **/
    public function get_person_details($id)
    {
        return $this->_response_request("catalog/people/{$id}");
    }
    
    /**
     * Retrieve detailed information about a User
     *
     * @param string $id (optional) The user id of the user.
     **/
    public function get_user($id = 'current')
    {
        return $this->_response_request("users/{$id}");
    }
    
    /**
     * Retrieve a list of subscriber feeds.
     *
     * @param string $id The user id of the user in question.
     * @param array $params (optional) Additional parameters. See the netflix API reference for details
     **/
    public function get_user_feeds($id, array $params = array())
    {
        $parstr = empty($params) ? '' : '?'.http_build_query($params);
        return $this->_response_request("users/{$id}/feeds{$parstr}");
    }
    
    /**
     * Retrieves a list of states for the titles in a users queue. These states will
     * either be 'available' for entries that are currently available for viewing or mail,
     * or 'saved' which can not currently be viewed or mailed.
     *
     * @param string $id The users id that is assigned by NetFlix.
     * @param array $params (optional) Additional parameters. See the netflix API reference for details
     **/
    public function get_user_title_states($id, array $params = array())
    {
        $parstr = empty($params) ? '' : '?'.http_build_query($params);
        return $this->_response_request("users/{$id}/title_states{$parstr}");
    }
    
    /***************************************************************************
     * User queue information please refer to
     * http://developer.netflix.com/docs/read/REST_API_Reference#0_20185
     * for more information about parameters and what each call will return.
     ***************************************************************************/
    
    /**
     * Retrieve a list of all available queues for the specified user.
     *
     * @param string $id The users id that is assigned by NetFlix.
     * @param string $type (optional) The type of queue to retrieve either 'instant' or 'disc'
     * @param array $params (optional) Additional parameters. See the netflix API reference for details
     **/
    public function get_queues($id, $type = '', array $params = array())
    {
        $parstr = empty($params) ? '' : '?'.http_build_query($params);
        $type = strlen($type) > 0 ? '/'.$type : $type;
        return $this->_response_request("users/{$id}/queues{$type}{$parstr}");
    }
    
    /**
     * Returns state details of titles in a users queue
     *
     * @param string $id The users id that is assigned by NetFlix.
     * @param string $type The type of queue to retrieve information on either 'instant' or 'disc'
     * @param string $state The queue state either 'available' or 'saved'
     * @param string $entry (optional) the entry to check availablility on.
     * @param array $params (optional) Additional parameters. See the netflix API reference for details
     **/
    public function get_queues_state($id, $type, $state, $entry = '', array $params = array())
    {
        $parstr = empty($params) ? '' : '?'.http_build_query($params);
        $entry = strlen($entry) > 0 ? '/'.$entry : $entry;
        return $this->_response_request("users/{$id}/queues/{$type}/{$state}{$entry}{$parstr}");
    }
    
    /**
     * Removes an entry from the queue.
     *
     * @param string $id The users id that is assigned by NetFlix.
     * @param string $type The type of queue to retrieve information on either 'instant' or 'disc'
     * @param string $state The queue state either 'available' or 'saved'
     * @param string $entry the entry to remove.
     * @param array $params (optional) Additional parameters. See the netflix API reference for details
     **/
    public function remove_queue_entry($id, $type, $state, $entry)
    {
        return $this->_response_request("users/{$id}/queues/{$type}/{$state}/{$entry}", 'DELETE');
    }
    
    /**
     * Add or move a title within a users queue. To move an entry use
     * the same information as adding except change the position.
     *
     * @param string $id The users id that is assigned by NetFlix.
     * @param string $type The type of queue to retrieve information on either 'instant' or 'disc'
     * @param string $title_ref the title URL
     * @param string $etag Netflix API returned this ETag value to you with the response to your most recent queue request (see http://developer.netflix.com/docs/REST_API_Reference#0_77163)
     * @param string $position The 1 based position of the title in the queue.
     **/
    public function add_queue_entry($id, $type, $title_ref, $etag, $position = '1')
    {
        $data = array('title_ref'=>$title_ref, 'position'=>$position, 'etag'=>$etag);
        return $this->_data_request("users/{$id}/queues/{$type}", $data);
    }
    
    /**
     * Returns the rental history (or recently watched instantly) for the specified user.
     *
     * @param string $id The users id that is assigned by NetFlix.
     * @param boolean $instant_watched (optional) True indicates you want instant queue or false (default) indicates disc queue.
     * @param array $params (optional) Additional parameters. See the netflix API reference for details
     **/
    public function get_rental_history($id, $instant_watched = false, array $params = array())
    {
        $parstr = empty($params) ? '' : '?'.http_build_query($params);
        $watched = $instant_watched === true ? '/watched' : '';
        return $this->_response_request("users/{$id}/rental_history{$watched}{$parstr}");
    }
    
    /**
     * Returns the rating for a particular title or titles.
     *
     * @param string $id The users id that is assigned by NetFlix.
     * @param string $title_refs A comma separated list of URL Ids to the movies you want.
     * @param boolean $predicted (optional) set to true to get the predicted rating for the title.
     **/
    public function get_title_rating($id, $title_refs, $predicted = false)
    {
        $title_refs = rawurlencode($title_refs);
        $predict = $predicted === false ? '' : '/predicted';
        return $this->_response_request("users/{$id}/ratings/title{$predict}?title_refs={$title_refs}");
    }
    
    /**
     * Sets or updates the rating for a particular title.
     *
     * @param string $id The users id that is assigned by NetFlix.
     * @param string $title_refs A comma separated list of URL Ids to the movies you want.
     * @param mixed $rating Must be an integer from 1 to 5, or 'not_interested'.
     * @param string $ratingId (optional) Specify the rating to update (only used to update a rating)
     **/
    public function set_title_rating($id, $title_ref, $rating, $ratingId = false)
    {
        $data = array('rating'=>$rating);
        if($ratingId !== false)
        {
            $data['title_ref'] = $title_ref;
            return $this->_data_request("users/{$id}/ratings/title/actual", $data);
        }
        else return $this->_data_request("users/{$id}/ratings/title/actual/{$ratingId}", $data, 'PUT');
    }
    
    /**
     * Gets recommended titles based on the users ratings.
     *
     * @param string $id The users id that is assigned by NetFlix.
     * @param array $params (optional) Additional parameters. See the netflix API reference for details
     **/
    public function get_recommendations($id, array $params = array())
    {
        $parstr = empty($params) ? '' : '?'.http_build_query($params);
        return $this->_response_request("users/{$id}/recommendations{$parstr}");
    }
    
    private function _response_request($uri, $method = 'GET')
    {
        $request = "{$method} {$uri} HTTP/".self::HTTP_1.self::LINE_END;
        $url = self::SCHEME.'://'.self::HOST.'/'.$uri;
        
        $header = $this->_build_header($url, $method, $request, self::LINE_END);
        
        $response = $this->_connect($url, $header, $method, false);
        return $response;
    }
    
    private function _data_request($uri, array $data, $method = 'POST')
    {
        $request = "{$method} {$uri} HTTP/".self::HTTP_1.self::LINE_END;
        $url = self::SCHEME.'://'.self::HOST.'/'.$uri;
        
        $header = $this->_build_header($url, $method, $request, self::LINE_END);
        
        $response = $this->_connect($url, $header, $method, $data);
        return $response;
    }
    
    private function _build_header($url, $method, $prepend, $append, $overwrite = array())
    {
        $str = $prepend === false ? '' : $prepend;
        foreach($this->_header AS $key=>$value)
        {
            if(array_key_exists($key, $overwrite))$str .= $key.': '.$overwrite[$key].self::LINE_END;
            else $str .= $key.': '.$value.self::LINE_END;
        }
        $str .= get_auth_header($url, $this->_consumer['key'], $this->_consumer['secret'], $this->_access, $method, $this->_consumer['algorithm']);
        $str .= $append === false ? '' : $append;

        return $str;
    }
    
    private function _connect($url, $header, $request, $postdata = false)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ;
        curl_setopt($ch, CURLOPT_SSLVERSION,3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request);
        curl_setopt($ch, CURLOPT_HTTPHEADER, explode(self::LINE_END, $header));
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        if(is_array($postdata))curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        
        $response = curl_exec($ch);
        
        if(self::DEBUG)
        {
            error_log(curl_getinfo($ch, CURLINFO_HEADER_OUT));
            error_log($response);
        }
        
        curl_close($ch);
        return $response;
    }
}
