<?php

/*
 * snoopy是一个php类，用来模仿web浏览器的功能，它能完成获取网页内容和发送表单的任务。
 * 官方网站 http://snoopy.sourceforge.net/
 * 相关介绍 http://www.nowamagic.net/librarys/veda/detail/855
 *
        $url="http://news.qq.com/a/20151009/007362.htm?tu_biz=1.114.1.0";
        snoopy::fetch($url,'http://www.baidu.com/');
        echo snoopy::put();

 * */

class Snoopy
{
    /**** Public variables ****/

    /* user definable vars */


    protected static $host			=	"www.baidu.com";		// host name we are connecting to
    protected static $port			=	80;					// port we are connecting to
    protected static $proxy_host		=	"";					// proxy host to use
    protected static $proxy_port		=	"";					// proxy port to use
    protected static $proxy_user		=	"";					// proxy user to use
    protected static $proxy_pass		=	"";					// proxy password to use

    protected static $agent			=	"Snoopy v1.2.4";	// agent we masquerade as
    protected static $referer		=	"";					// referer info to pass
    protected static $cookies		=	array();			// array of cookies to pass
    // $cookies["username"]="joe";
    protected static $rawheaders		=	array();			// array of raw headers to send
    // $rawheaders["Content-type"]="text/html";

    protected static $maxredirs		=	5;					// http redirection depth maximum. 0 = disallow
    protected static $lastredirectaddr	=	"";				// contains address of last redirected address
    protected static $offsiteok		=	true;				// allows redirection off-site
    protected static $maxframes		=	0;					// frame content depth maximum. 0 = disallow
    protected static $expandlinks	=	true;				// expand links to fully qualified URLs.
    // this only applies to fetchlinks()
    // submitlinks(), and submittext()
    protected static $passcookies	=	true;				// pass set cookies back through redirects
    // NOTE: this currently does not respect
    // dates, domains or paths.

    protected static $user			=	"";					// user for http authentication
    protected static $pass			=	"";					// password for http authentication

    // http accept types
    protected static $accept			=	"image/gif, image/x-xbitmap, image/jpeg, image/pjpeg, */*";

    protected static $results		=	"";					// where the content is put

    protected static $error			=	"";					// error messages sent here
    protected static $response_code	=	"";					// response code returned from server
    protected static $headers		=	array();			// headers returned from server sent here
    protected static $maxlength		=	500000;				// max return data length (body)
    protected static $read_timeout	=	0;					// timeout on read operations, in seconds
    // supported only since PHP 4 Beta 4
    // set to 0 to disallow timeouts
    protected static $timed_out		=	false;				// if a read operation timed out
    protected static $status			=	0;					// http request status

    protected static $temp_dir		=	"/tmp";				// temporary directory that the webserver
    // has permission to write to.
    // under Windows, this should be C:\temp

    protected static $curl_path		=	"/usr/local/bin/curl";
    // Snoopy will use cURL for fetching
    // SSL content if a full system path to
    // the cURL binary is supplied here.
    // set to false if you do not have
    // cURL installed. See http://curl.haxx.se
    // for details on installing cURL.
    // Snoopy does *not* use the cURL
    // library functions built into php,
    // as these functions are not stable
    // as of this Snoopy release.

    /**** Private variables ****/

    protected static $_maxlinelen	=	4096;				// max line length (headers)

    protected static $_httpmethod	=	"GET";				// default http request method
    protected static $_httpversion	=	"HTTP/1.0";			// default http request version
    protected static $_submit_method	="POST";				// default submit method
    protected static $_submit_type	=	"application/x-www-form-urlencoded";	// default submit type
    protected static $_mime_boundary	=   "";					// MIME boundary for multipart/form-data submit type
    protected static $_redirectaddr	=	false;				// will be set if page fetched is a redirect
    protected static $_redirectdepth	=	0;					// increments on an http redirect
    protected static $_frameurls		= 	array();			// frame src urls
    protected static $_framedepth	=	0;					// increments on frame depth

    protected static $_isproxy		=	false;				// set if using a proxy server
    protected static $_fp_timeout	=	30;					// timeout for socket connection



    //输出内容
    public static function put(){
        return self::$results;
    }

    /*======================================================================*\
        Function:	fetch
        Purpose:	fetch the contents of a web page
                    (and possibly other protocols in the
                    future like ftp, nntp, gopher, etc.)
        Input:		$URI	the location of the page to fetch
        Output:		self::$results	the output text from the fetch
    \*======================================================================*/

    public static function fetch($URI,$Referer=null)
    {
        if($Referer)self::$referer = $Referer;//伪装来源

        //preg_match("|^([^:]+)://([^:/]+)(:[\d]+)*(.*)|",$URI,$URI_PARTS);
        $URI_PARTS = parse_url($URI);
        if (!empty($URI_PARTS["user"]))
            self::$user = $URI_PARTS["user"];
        if (!empty($URI_PARTS["pass"]))
            self::$pass = $URI_PARTS["pass"];
        if (empty($URI_PARTS["query"]))
            $URI_PARTS["query"] = '';
        if (empty($URI_PARTS["path"]))
            $URI_PARTS["path"] = '';

        switch(strtolower($URI_PARTS["scheme"]))
        {
            case "http":
                self::$host = $URI_PARTS["host"];
                if(!empty($URI_PARTS["port"]))
                    self::$port = $URI_PARTS["port"];
                if(self::_connect($fp))
                {
                    if(self::$_isproxy)
                    {
                        // using proxy, send entire URI
                        self::_httprequest($URI,$fp,$URI,self::$_httpmethod);
                    }
                    else
                    {
                        $path = $URI_PARTS["path"].($URI_PARTS["query"] ? "?".$URI_PARTS["query"] : "");
                        // no proxy, send only the path
                        self::_httprequest($path, $fp, $URI, self::$_httpmethod);
                    }

                    self::_disconnect($fp);

                    if(self::$_redirectaddr)
                    {
                        /* url was redirected, check if we've hit the max depth */
                        if(self::$maxredirs > self::$_redirectdepth)
                        {
                            // only follow redirect if it's on this site, or offsiteok is true
                            if(preg_match("|^http://".preg_quote(self::$host)."|i",self::$_redirectaddr) || self::$offsiteok)
                            {
                                /* follow the redirect */
                                self::$_redirectdepth++;
                                self::$lastredirectaddr=self::$_redirectaddr;
                                self::fetch(self::$_redirectaddr);
                            }
                        }
                    }

                    if(self::$_framedepth < self::$maxframes && count(self::$_frameurls) > 0)
                    {
                        $frameurls = self::$_frameurls;
                        self::$_frameurls = array();

                        while(list(,$frameurl) = each($frameurls))
                        {
                            if(self::$_framedepth < self::$maxframes)
                            {
                                self::fetch($frameurl);
                                self::$_framedepth++;
                            }
                            else
                                break;
                        }
                    }
                }
                else
                {
                    return false;
                }
                return true;
                break;
            case "https":
                if(!self::$curl_path)
                    return false;
                if(function_exists("is_executable"))
                    if (!is_executable(self::$curl_path))
                        return false;
                self::$host = $URI_PARTS["host"];
                if(!empty($URI_PARTS["port"]))
                    self::$port = $URI_PARTS["port"];
                if(self::$_isproxy)
                {
                    // using proxy, send entire URI
                    self::_httpsrequest($URI,$URI,self::$_httpmethod);
                }
                else
                {
                    $path = $URI_PARTS["path"].($URI_PARTS["query"] ? "?".$URI_PARTS["query"] : "");
                    // no proxy, send only the path
                    self::_httpsrequest($path, $URI, self::$_httpmethod);
                }

                if(self::$_redirectaddr)
                {
                    /* url was redirected, check if we've hit the max depth */
                    if(self::$maxredirs > self::$_redirectdepth)
                    {
                        // only follow redirect if it's on this site, or offsiteok is true
                        if(preg_match("|^http://".preg_quote(self::$host)."|i",self::$_redirectaddr) || self::$offsiteok)
                        {
                            /* follow the redirect */
                            self::$_redirectdepth++;
                            self::$lastredirectaddr=self::$_redirectaddr;
                            self::fetch(self::$_redirectaddr);
                        }
                    }
                }

                if(self::$_framedepth < self::$maxframes && count(self::$_frameurls) > 0)
                {
                    $frameurls = self::$_frameurls;
                    self::$_frameurls = array();

                    while(list(,$frameurl) = each($frameurls))
                    {
                        if(self::$_framedepth < self::$maxframes)
                        {
                            self::fetch($frameurl);
                            self::$_framedepth++;
                        }
                        else
                            break;
                    }
                }
                return true;
                break;
            default:
                // not a valid protocol
                self::$error	=	'Invalid protocol "'.$URI_PARTS["scheme"].'"\n';
                return false;
                break;
        }
        //return true;
    }

    /*======================================================================*\
        Function:	submit
        Purpose:	submit an http form
        Input:		$URI	the location to post the data
                    $formvars	the formvars to use.
                        format: $formvars["var"] = "val";
                    $formfiles  an array of files to submit
                        format: $formfiles["var"] = "/dir/filename.ext";
        Output:		self::$results	the text output from the post
    \*======================================================================*/

    private static function submit($URI, $formvars="", $formfiles="")
    {
        unset($postdata);

        $postdata = self::_prepare_post_body($formvars, $formfiles);

        $URI_PARTS = parse_url($URI);
        if (!empty($URI_PARTS["user"]))
            self::$user = $URI_PARTS["user"];
        if (!empty($URI_PARTS["pass"]))
            self::$pass = $URI_PARTS["pass"];
        if (empty($URI_PARTS["query"]))
            $URI_PARTS["query"] = '';
        if (empty($URI_PARTS["path"]))
            $URI_PARTS["path"] = '';

        switch(strtolower($URI_PARTS["scheme"]))
        {
            case "http":
                self::$host = $URI_PARTS["host"];
                if(!empty($URI_PARTS["port"]))
                    self::$port = $URI_PARTS["port"];
                if(self::_connect($fp))
                {
                    if(self::$_isproxy)
                    {
                        // using proxy, send entire URI
                        self::_httprequest($URI,$fp,$URI,self::$_submit_method,self::$_submit_type,$postdata);
                    }
                    else
                    {
                        $path = $URI_PARTS["path"].($URI_PARTS["query"] ? "?".$URI_PARTS["query"] : "");
                        // no proxy, send only the path
                        self::_httprequest($path, $fp, $URI, self::$_submit_method, self::$_submit_type, $postdata);
                    }

                    self::_disconnect($fp);

                    if(self::$_redirectaddr)
                    {
                        /* url was redirected, check if we've hit the max depth */
                        if(self::$maxredirs > self::$_redirectdepth)
                        {
                            if(!preg_match("|^".$URI_PARTS["scheme"]."://|", self::$_redirectaddr))
                                self::$_redirectaddr = self::_expandlinks(self::$_redirectaddr,$URI_PARTS["scheme"]."://".$URI_PARTS["host"]);

                            // only follow redirect if it's on this site, or offsiteok is true
                            if(preg_match("|^http://".preg_quote(self::$host)."|i",self::$_redirectaddr) || self::$offsiteok)
                            {
                                /* follow the redirect */
                                self::$_redirectdepth++;
                                self::$lastredirectaddr=self::$_redirectaddr;
                                if( strpos( self::$_redirectaddr, "?" ) > 0 )
                                    self::fetch(self::$_redirectaddr); // the redirect has changed the request method from post to get
                                else
                                    self::submit(self::$_redirectaddr,$formvars, $formfiles);
                            }
                        }
                    }

                    if(self::$_framedepth < self::$maxframes && count(self::$_frameurls) > 0)
                    {
                        $frameurls = self::$_frameurls;
                        self::$_frameurls = array();

                        while(list(,$frameurl) = each($frameurls))
                        {
                            if(self::$_framedepth < self::$maxframes)
                            {
                                self::fetch($frameurl);
                                self::$_framedepth++;
                            }
                            else
                                break;
                        }
                    }

                }
                else
                {
                    return false;
                }
                return true;
                break;
            case "https":
                if(!self::$curl_path)
                    return false;
                if(function_exists("is_executable"))
                    if (!is_executable(self::$curl_path))
                        return false;
                self::$host = $URI_PARTS["host"];
                if(!empty($URI_PARTS["port"]))
                    self::$port = $URI_PARTS["port"];
                if(self::$_isproxy)
                {
                    // using proxy, send entire URI
                    self::_httpsrequest($URI, $URI, self::$_submit_method, self::$_submit_type, $postdata);
                }
                else
                {
                    $path = $URI_PARTS["path"].($URI_PARTS["query"] ? "?".$URI_PARTS["query"] : "");
                    // no proxy, send only the path
                    self::_httpsrequest($path, $URI, self::$_submit_method, self::$_submit_type, $postdata);
                }

                if(self::$_redirectaddr)
                {
                    /* url was redirected, check if we've hit the max depth */
                    if(self::$maxredirs > self::$_redirectdepth)
                    {
                        if(!preg_match("|^".$URI_PARTS["scheme"]."://|", self::$_redirectaddr))
                            self::$_redirectaddr = self::_expandlinks(self::$_redirectaddr,$URI_PARTS["scheme"]."://".$URI_PARTS["host"]);

                        // only follow redirect if it's on this site, or offsiteok is true
                        if(preg_match("|^http://".preg_quote(self::$host)."|i",self::$_redirectaddr) || self::$offsiteok)
                        {
                            /* follow the redirect */
                            self::$_redirectdepth++;
                            self::$lastredirectaddr=self::$_redirectaddr;
                            if( strpos( self::$_redirectaddr, "?" ) > 0 )
                                self::fetch(self::$_redirectaddr); // the redirect has changed the request method from post to get
                            else
                                self::submit(self::$_redirectaddr,$formvars, $formfiles);
                        }
                    }
                }

                if(self::$_framedepth < self::$maxframes && count(self::$_frameurls) > 0)
                {
                    $frameurls = self::$_frameurls;
                    self::$_frameurls = array();

                    while(list(,$frameurl) = each($frameurls))
                    {
                        if(self::$_framedepth < self::$maxframes)
                        {
                            self::fetch($frameurl);
                            self::$_framedepth++;
                        }
                        else
                            break;
                    }
                }
                return true;
                break;

            default:
                // not a valid protocol
                self::$error	=	'Invalid protocol "'.$URI_PARTS["scheme"].'"\n';
                return false;
                break;
        }
        //return true;
    }

    /*======================================================================*\
        Function:	fetchlinks
        Purpose:	fetch the links from a web page
        Input:		$URI	where you are fetching from
        Output:		self::$results	an array of the URLs
    \*======================================================================*/

    private static function fetchlinks($URI)
    {
        if (self::fetch($URI))
        {
            if(self::$lastredirectaddr)
                $URI = self::$lastredirectaddr;
            if(is_array(self::$results))
            {
                for($x=0;$x<count(self::$results);$x++)
                    self::$results[$x] = self::_striplinks(self::$results[$x]);
            }
            else
                self::$results = self::_striplinks(self::$results);

            if(self::$expandlinks)
                self::$results = self::_expandlinks(self::$results, $URI);
            return true;
        }
        else
            return false;
    }

    /*======================================================================*\
        Function:	fetchform
        Purpose:	fetch the form elements from a web page
        Input:		$URI	where you are fetching from
        Output:		self::$results	the resulting html form
    \*======================================================================*/

    private static function fetchform($URI)
    {

        if (self::fetch($URI))
        {

            if(is_array(self::$results))
            {
                for($x=0;$x<count(self::$results);$x++)
                    self::$results[$x] = self::_stripform(self::$results[$x]);
            }
            else
                self::$results = self::_stripform(self::$results);

            return true;
        }
        else
            return false;
    }


    /*======================================================================*\
        Function:	fetchtext
        Purpose:	fetch the text from a web page, stripping the links
        Input:		$URI	where you are fetching from
        Output:		self::$results	the text from the web page
    \*======================================================================*/

    private static function fetchtext($URI)
    {
        if(self::fetch($URI))
        {
            if(is_array(self::$results))
            {
                for($x=0;$x<count(self::$results);$x++)
                    self::$results[$x] = self::_striptext(self::$results[$x]);
            }
            else
                self::$results = self::_striptext(self::$results);
            return true;
        }
        else
            return false;
    }

    /*======================================================================*\
        Function:	submitlinks
        Purpose:	grab links from a form submission
        Input:		$URI	where you are submitting from
        Output:		self::$results	an array of the links from the post
    \*======================================================================*/

    private static function submitlinks($URI, $formvars="", $formfiles="")
    {
        if(self::submit($URI,$formvars, $formfiles))
        {
            if(self::$lastredirectaddr)
                $URI = self::$lastredirectaddr;
            if(is_array(self::$results))
            {
                for($x=0;$x<count(self::$results);$x++)
                {
                    self::$results[$x] = self::_striplinks(self::$results[$x]);
                    if(self::$expandlinks)
                        self::$results[$x] = self::_expandlinks(self::$results[$x],$URI);
                }
            }
            else
            {
                self::$results = self::_striplinks(self::$results);
                if(self::$expandlinks)
                    self::$results = self::_expandlinks(self::$results,$URI);
            }
            return true;
        }
        else
            return false;
    }

    /*======================================================================*\
        Function:	submittext
        Purpose:	grab text from a form submission
        Input:		$URI	where you are submitting from
        Output:		self::$results	the text from the web page
    \*======================================================================*/

    private static function submittext($URI, $formvars = "", $formfiles = "")
    {
        if(self::submit($URI,$formvars, $formfiles))
        {
            if(self::$lastredirectaddr)
                $URI = self::$lastredirectaddr;
            if(is_array(self::$results))
            {
                for($x=0;$x<count(self::$results);$x++)
                {
                    self::$results[$x] = self::_striptext(self::$results[$x]);
                    if(self::$expandlinks)
                        self::$results[$x] = self::_expandlinks(self::$results[$x],$URI);
                }
            }
            else
            {
                self::$results = self::_striptext(self::$results);
                if(self::$expandlinks)
                    self::$results = self::_expandlinks(self::$results,$URI);
            }
            return true;
        }
        else
            return false;
    }



    /*======================================================================*\
        Function:	set_submit_multipart
        Purpose:	Set the form submission content type to
                    multipart/form-data
    \*======================================================================*/
    private static function set_submit_multipart()
    {
        self::$_submit_type = "multipart/form-data";
    }


    /*======================================================================*\
        Function:	set_submit_normal
        Purpose:	Set the form submission content type to
                    application/x-www-form-urlencoded
    \*======================================================================*/
    private static function set_submit_normal()
    {
        self::$_submit_type = "application/x-www-form-urlencoded";
    }




    /*======================================================================*\
        Private functions
    \*======================================================================*/


    /*======================================================================*\
        Function:	_striplinks
        Purpose:	strip the hyperlinks from an html document
        Input:		$document	document to strip.
        Output:		$match		an array of the links
    \*======================================================================*/

    private static function _striplinks($document)
    {
        preg_match_all("'<\s*a\s.*?href\s*=\s*			# find <a href=
						([\"\'])?					# find single or double quote
						(?(1) (.*?)\\1 | ([^\s\>]+))		# if quote found, match up to next matching
													# quote, otherwise match up to next space
						'isx",$document,$links);


        // catenate the non-empty matches from the conditional subpattern

        while(list($key,$val) = each($links[2]))
        {
            if(!empty($val))
                $match[] = $val;
        }

        while(list($key,$val) = each($links[3]))
        {
            if(!empty($val))
                $match[] = $val;
        }

        // return the links
        return $match;
    }

    /*======================================================================*\
        Function:	_stripform
        Purpose:	strip the form elements from an html document
        Input:		$document	document to strip.
        Output:		$match		an array of the links
    \*======================================================================*/

    private static function _stripform($document)
    {
        preg_match_all("'<\/?(FORM|INPUT|SELECT|TEXTAREA|(OPTION))[^<>]*>(?(2)(.*(?=<\/?(option|select)[^<>]*>[\r\n]*)|(?=[\r\n]*))|(?=[\r\n]*))'Usi",$document,$elements);

        // catenate the matches
        $match = implode("\r\n",$elements[0]);

        // return the links
        return $match;
    }



    /*======================================================================*\
        Function:	_striptext
        Purpose:	strip the text from an html document
        Input:		$document	document to strip.
        Output:		$text		the resulting text
    \*======================================================================*/

    private static function _striptext($document)
    {

        // I didn't use preg eval (//e) since that is only available in PHP 4.0.
        // so, list your entities one by one here. I included some of the
        // more common ones.

        $search = array("'<script[^>]*?>.*?</script>'si",	// strip out javascript
            "'<[\/\!]*?[^<>]*?>'si",			// strip out html tags
            "'([\r\n])[\s]+'",					// strip out white space
            "'&(quot|#34|#034|#x22);'i",		// replace html entities
            "'&(amp|#38|#038|#x26);'i",			// added hexadecimal values
            "'&(lt|#60|#060|#x3c);'i",
            "'&(gt|#62|#062|#x3e);'i",
            "'&(nbsp|#160|#xa0);'i",
            "'&(iexcl|#161);'i",
            "'&(cent|#162);'i",
            "'&(pound|#163);'i",
            "'&(copy|#169);'i",
            "'&(reg|#174);'i",
            "'&(deg|#176);'i",
            "'&(#39|#039|#x27);'",
            "'&(euro|#8364);'i",				// europe
            "'&a(uml|UML);'",					// german
            "'&o(uml|UML);'",
            "'&u(uml|UML);'",
            "'&A(uml|UML);'",
            "'&O(uml|UML);'",
            "'&U(uml|UML);'",
            "'&szlig;'i",
        );
        $replace = array(	"",
            "",
            "\\1",
            "\"",
            "&",
            "<",
            ">",
            " ",
            chr(161),
            chr(162),
            chr(163),
            chr(169),
            chr(174),
            chr(176),
            chr(39),
            chr(128),
            "ä",
            "ö",
            "ü",
            "Ä",
            "Ö",
            "Ü",
            "ß",
        );

        $text = preg_replace($search,$replace,$document);

        return $text;
    }

    /*======================================================================*\
        Function:	_expandlinks
        Purpose:	expand each link into a fully qualified URL
        Input:		$links			the links to qualify
                    $URI			the full URI to get the base from
        Output:		$expandedLinks	the expanded links
    \*======================================================================*/

    private static function _expandlinks($links,$URI)
    {

        preg_match("/^[^\?]+/",$URI,$match);

        $match = preg_replace("|/[^\/\.]+\.[^\/\.]+$|","",$match[0]);
        $match = preg_replace("|/$|","",$match);
        $match_part = parse_url($match);
        $match_root =
            $match_part["scheme"]."://".$match_part["host"];

        $search = array( 	"|^http://".preg_quote(self::$host)."|i",
            "|^(\/)|i",
            "|^(?!http://)(?!mailto:)|i",
            "|/\./|",
            "|/[^\/]+/\.\./|"
        );

        $replace = array(	"",
            $match_root."/",
            $match."/",
            "/",
            "/"
        );

        $expandedLinks = preg_replace($search,$replace,$links);

        return $expandedLinks;
    }

    /*======================================================================*\
        Function:	_httprequest
        Purpose:	go get the http data from the server
        Input:		$url		the url to fetch
                    $fp			the current open file pointer
                    $URI		the full URI
                    $body		body contents to send if any (POST)
        Output:
    \*======================================================================*/

    private static function _httprequest($url,$fp,$URI,$http_method,$content_type="",$body="")
    {
        $cookie_headers = '';
        if(self::$passcookies && self::$_redirectaddr)
            self::setcookies();

        $URI_PARTS = parse_url($URI);
        if(empty($url))
            $url = "/";
        $headers = $http_method." ".$url." ".self::$_httpversion."\r\n";
        if(!empty(self::$agent))
            $headers .= "User-Agent: ".self::$agent."\r\n";
        if(!empty(self::$host) && !isset(self::$rawheaders['Host'])) {
            $headers .= "Host: ".self::$host;
            if(!empty(self::$port))
                $headers .= ":".self::$port;
            $headers .= "\r\n";
        }
        if(!empty(self::$accept))
            $headers .= "Accept: ".self::$accept."\r\n";
        if(!empty(self::$referer))
            $headers .= "Referer: ".self::$referer."\r\n";
        if(!empty(self::$cookies))
        {
            if(!is_array(self::$cookies))
                self::$cookies = (array)self::$cookies;

            reset(self::$cookies);
            if ( count(self::$cookies) > 0 ) {
                $cookie_headers .= 'Cookie: ';
                foreach ( self::$cookies as $cookieKey => &$cookieVal ) {
                    $cookie_headers .= $cookieKey."=".urlencode($cookieVal)."; ";
                }
                $headers .= substr($cookie_headers,0,-2) . "\r\n";
            }
        }
        if(!empty(self::$rawheaders))
        {
            if(!is_array(self::$rawheaders))
                self::$rawheaders = (array)self::$rawheaders;
            while(list($headerKey,$headerVal) = each(self::$rawheaders))
                $headers .= $headerKey.": ".$headerVal."\r\n";
        }
        if(!empty($content_type)) {
            $headers .= "Content-type: $content_type";
            if ($content_type == "multipart/form-data")
                $headers .= "; boundary=".self::$_mime_boundary;
            $headers .= "\r\n";
        }
        if(!empty($body))
            $headers .= "Content-length: ".strlen($body)."\r\n";
        if(!empty(self::$user) || !empty(self::$pass))
            $headers .= "Authorization: Basic ".base64_encode(self::$user.":".self::$pass)."\r\n";

        //add proxy auth headers
        if(!empty(self::$proxy_user))
            $headers .= 'Proxy-Authorization: ' . 'Basic ' . base64_encode(self::$proxy_user . ':' . self::$proxy_pass)."\r\n";


        $headers .= "\r\n";

        // set the read timeout if needed
        if (self::$read_timeout > 0)
            socket_set_timeout($fp, self::$read_timeout);
        self::$timed_out = false;

        fwrite($fp,$headers.$body,strlen($headers.$body));

        self::$_redirectaddr = false;
        //unset(self::$headers);
        self::$headers=null;

        while($currentHeader = fgets($fp,self::$_maxlinelen))
        {
            if (self::$read_timeout > 0 && self::_check_timeout($fp))
            {
                self::$status=-100;
                return false;
            }

            if($currentHeader == "\r\n")
                break;

            // if a header begins with Location: or URI:, set the redirect
            if(preg_match("/^(Location:|URI:)/i",$currentHeader))
            {
                // get URL portion of the redirect
                preg_match("/^(Location:|URI:)[ ]+(.*)/i",chop($currentHeader),$matches);
                // look for :// in the Location header to see if hostname is included
                if(!preg_match("|\:\/\/|",$matches[2]))
                {
                    // no host in the path, so prepend
                    self::$_redirectaddr = $URI_PARTS["scheme"]."://".self::$host.":".self::$port;
                    // eliminate double slash
                    if(!preg_match("|^/|",$matches[2]))
                        self::$_redirectaddr .= "/".$matches[2];
                    else
                        self::$_redirectaddr .= $matches[2];
                }
                else
                    self::$_redirectaddr = $matches[2];
            }

            if(preg_match("|^HTTP/|",$currentHeader))
            {
                if(preg_match("|^HTTP/[^\s]*\s(.*?)\s|",$currentHeader, $status))
                {
                    self::$status= $status[1];
                }
                self::$response_code = $currentHeader;
            }

            self::$headers[] = $currentHeader;
        }

        $results = '';
        do {
            $_data = fread($fp, self::$maxlength);
            if (strlen($_data) == 0) {
                break;
            }
            $results .= $_data;
        } while(true);

        if (self::$read_timeout > 0 && self::_check_timeout($fp))
        {
            self::$status=-100;
            return false;
        }

        // check if there is a a redirect meta tag

        if(preg_match("'<meta[\s]*http-equiv[^>]*?content[\s]*=[\s]*[\"\']?\d+;[\s]*URL[\s]*=[\s]*([^\"\']*?)[\"\']?>'i",$results,$match))

        {
            self::$_redirectaddr = self::_expandlinks($match[1],$URI);
        }

        // have we hit our frame depth and is there frame src to fetch?
        if((self::$_framedepth < self::$maxframes) && preg_match_all("'<frame\s+.*src[\s]*=[\'\"]?([^\'\"\>]+)'i",$results,$match))
        {
            self::$results[] = $results;
            for($x=0; $x<count($match[1]); $x++)
                self::$_frameurls[] = self::_expandlinks($match[1][$x],$URI_PARTS["scheme"]."://".self::$host);
        }
        // have we already fetched framed content?
        elseif(is_array(self::$results))
            self::$results[] = $results;
        // no framed content
        else
            self::$results = $results;

        return true;
    }

    /*======================================================================*\
        Function:	_httpsrequest
        Purpose:	go get the https data from the server using curl
        Input:		$url		the url to fetch
                    $URI		the full URI
                    $body		body contents to send if any (POST)
        Output:
    \*======================================================================*/

    private static function _httpsrequest($url,$URI,$http_method,$content_type="",$body="")
    {
        if(self::$passcookies && self::$_redirectaddr)
            self::setcookies();

        $headers = array();

        $URI_PARTS = parse_url($URI);
        if(empty($url))
            $url = "/";
        // GET ... header not needed for curl
        //$headers[] = $http_method." ".$url." ".self::$_httpversion;
        if(!empty(self::$agent))
            $headers[] = "User-Agent: ".self::$agent;
        if(!empty(self::$host))
            if(!empty(self::$port))
                $headers[] = "Host: ".self::$host.":".self::$port;
            else
                $headers[] = "Host: ".self::$host;
        if(!empty(self::$accept))
            $headers[] = "Accept: ".self::$accept;
        if(!empty(self::$referer))
            $headers[] = "Referer: ".self::$referer;
        if(!empty(self::$cookies))
        {
            if(!is_array(self::$cookies))
                self::$cookies = (array)self::$cookies;

            reset(self::$cookies);
            if ( count(self::$cookies) > 0 ) {
                $cookie_str = 'Cookie: ';
                foreach ( self::$cookies as $cookieKey => &$cookieVal ) {
                    $cookie_str .= $cookieKey."=".urlencode($cookieVal)."; ";
                }
                $headers[] = substr($cookie_str,0,-2);
            }
        }
        if(!empty(self::$rawheaders))
        {
            if(!is_array(self::$rawheaders))
                self::$rawheaders = (array)self::$rawheaders;
            while(list($headerKey,$headerVal) = each(self::$rawheaders))
                $headers[] = $headerKey.": ".$headerVal;
        }
        if(!empty($content_type)) {
            if ($content_type == "multipart/form-data")
                $headers[] = "Content-type: $content_type; boundary=".self::$_mime_boundary;
            else
                $headers[] = "Content-type: $content_type";
        }
        if(!empty($body))
            $headers[] = "Content-length: ".strlen($body);
        if(!empty(self::$user) || !empty(self::$pass))
            $headers[] = "Authorization: BASIC ".base64_encode(self::$user.":".self::$pass);

        $cmdline_params='';
        for($curr_header = 0; $curr_header < count($headers); $curr_header++) {
            $safer_header = strtr( $headers[$curr_header], "\"", " " );
            $cmdline_params .= " -H \"".$safer_header."\"";
        }

        if(!empty($body))
            $cmdline_params .= " -d \"$body\"";

        if(self::$read_timeout > 0)
            $cmdline_params .= " -m ".self::$read_timeout;

        $headerfile = tempnam($temp_dir, "sno");

        exec(self::$curl_path." -k -D \"$headerfile\"".$cmdline_params." \"".escapeshellcmd($URI)."\"",$results,$return);

        if($return)
        {
            self::$error = "Error: cURL could not retrieve the document, error $return.";
            return false;
        }


        $results = implode("\r\n",$results);

        $result_headers = file("$headerfile");

        self::$_redirectaddr = false;
        unset(self::$headers);

        for($currentHeader = 0; $currentHeader < count($result_headers); $currentHeader++)
        {

            // if a header begins with Location: or URI:, set the redirect
            if(preg_match("/^(Location: |URI: )/i",$result_headers[$currentHeader]))
            {
                // get URL portion of the redirect
                preg_match("/^(Location: |URI:)\s+(.*)/",chop($result_headers[$currentHeader]),$matches);
                // look for :// in the Location header to see if hostname is included
                if(!preg_match("|\:\/\/|",$matches[2]))
                {
                    // no host in the path, so prepend
                    self::$_redirectaddr = $URI_PARTS["scheme"]."://".self::$host.":".self::$port;
                    // eliminate double slash
                    if(!preg_match("|^/|",$matches[2]))
                        self::$_redirectaddr .= "/".$matches[2];
                    else
                        self::$_redirectaddr .= $matches[2];
                }
                else
                    self::$_redirectaddr = $matches[2];
            }

            if(preg_match("|^HTTP/|",$result_headers[$currentHeader]))
                self::$response_code = $result_headers[$currentHeader];

            self::$headers[] = $result_headers[$currentHeader];
        }

        // check if there is a a redirect meta tag

        if(preg_match("'<meta[\s]*http-equiv[^>]*?content[\s]*=[\s]*[\"\']?\d+;[\s]*URL[\s]*=[\s]*([^\"\']*?)[\"\']?>'i",$results,$match))
        {
            self::$_redirectaddr = self::_expandlinks($match[1],$URI);
        }

        // have we hit our frame depth and is there frame src to fetch?
        if((self::$_framedepth < self::$maxframes) && preg_match_all("'<frame\s+.*src[\s]*=[\'\"]?([^\'\"\>]+)'i",$results,$match))
        {
            self::$results[] = $results;
            for($x=0; $x<count($match[1]); $x++)
                self::$_frameurls[] = self::_expandlinks($match[1][$x],$URI_PARTS["scheme"]."://".self::$host);
        }
        // have we already fetched framed content?
        elseif(is_array(self::$results))
            self::$results[] = $results;
        // no framed content
        else
            self::$results = $results;

        unlink("$headerfile");

        return true;
    }

    /*======================================================================*\
        Function:	setcookies()
        Purpose:	set cookies for a redirection
    \*======================================================================*/

    private static function setcookies()
    {
        for($x=0; $x<count(self::$headers); $x++)
        {
            if(preg_match('/^set-cookie:[\s]+([^=]+)=([^;]+)/i', self::$headers[$x],$match))
                self::$cookies[$match[1]] = urldecode($match[2]);
        }
    }


    /*======================================================================*\
        Function:	_check_timeout
        Purpose:	checks whether timeout has occurred
        Input:		$fp	file pointer
    \*======================================================================*/

    private static function _check_timeout($fp)
    {
        if (self::$read_timeout > 0) {
            $fp_status = socket_get_status($fp);
            if ($fp_status["timed_out"]) {
                self::$timed_out = true;
                return true;
            }
        }
        return false;
    }

    /*======================================================================*\
        Function:	_connect
        Purpose:	make a socket connection
        Input:		$fp	file pointer
    \*======================================================================*/

    private static function _connect(&$fp)
    {
        if(!empty(self::$proxy_host) && !empty(self::$proxy_port))
        {
            self::$_isproxy = true;

            $host = self::$proxy_host;
            $port = self::$proxy_port;
        }
        else
        {
            $host = self::$host;
            $port = self::$port;
        }

        self::$status = 0;

        if($fp = fsockopen(
            $host,
            $port,
            $errno,
            $errstr,
            self::$_fp_timeout
        ))
        {
            // socket connection succeeded

            return true;
        }
        else
        {
            // socket connection failed
            self::$status = $errno;
            switch($errno)
            {
                case -3:
                    self::$error="socket creation failed (-3)";
                    break;
                case -4:
                    self::$error="dns lookup failure (-4)";
                    break;
                case -5:
                    self::$error="connection refused or timed out (-5)";
                    break;
                default:
                    self::$error="connection failed (".$errno.")";
            }
            return false;
        }
    }
    /*======================================================================*\
        Function:	_disconnect
        Purpose:	disconnect a socket connection
        Input:		$fp	file pointer
    \*======================================================================*/

    private static function _disconnect($fp)
    {
        return(fclose($fp));
    }


    /*======================================================================*\
        Function:	_prepare_post_body
        Purpose:	Prepare post body according to encoding type
        Input:		$formvars  - form variables
                    $formfiles - form upload files
        Output:		post body
    \*======================================================================*/

    private static function _prepare_post_body($formvars, $formfiles)
    {
        settype($formvars, "array");
        settype($formfiles, "array");
        $postdata = '';

        if (count($formvars) == 0 && count($formfiles) == 0)
            return '';

        switch (self::$_submit_type) {
            case "application/x-www-form-urlencoded":
                reset($formvars);
                while(list($key,$val) = each($formvars)) {
                    if (is_array($val) || is_object($val)) {
                        while (list($cur_key, $cur_val) = each($val)) {
                            $postdata .= urlencode($key)."[]=".urlencode($cur_val)."&";
                        }
                    } else
                        $postdata .= urlencode($key)."=".urlencode($val)."&";
                }
                break;

            case "multipart/form-data":
                self::$_mime_boundary = "Snoopy".md5(uniqid(microtime()));

                reset($formvars);
                while(list($key,$val) = each($formvars)) {
                    if (is_array($val) || is_object($val)) {
                        while (list($cur_key, $cur_val) = each($val)) {
                            $postdata .= "--".self::$_mime_boundary."\r\n";
                            $postdata .= "Content-Disposition: form-data; name=\"$key\[\]\"\r\n\r\n";
                            $postdata .= "$cur_val\r\n";
                        }
                    } else {
                        $postdata .= "--".self::$_mime_boundary."\r\n";
                        $postdata .= "Content-Disposition: form-data; name=\"$key\"\r\n\r\n";
                        $postdata .= "$val\r\n";
                    }
                }

                reset($formfiles);
                while (list($field_name, $file_names) = each($formfiles)) {
                    settype($file_names, "array");
                    while (list(, $file_name) = each($file_names)) {
                        if (!is_readable($file_name)) continue;

                        $fp = fopen($file_name, "r");
                        $file_content = fread($fp, filesize($file_name));
                        fclose($fp);
                        $base_name = basename($file_name);

                        $postdata .= "--".self::$_mime_boundary."\r\n";
                        $postdata .= "Content-Disposition: form-data; name=\"$field_name\"; filename=\"$base_name\"\r\n\r\n";
                        $postdata .= "$file_content\r\n";
                    }
                }
                $postdata .= "--".self::$_mime_boundary."--\r\n";
                break;
        }

        return $postdata;
    }
}

