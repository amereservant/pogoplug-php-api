<?php
session_start();

/**
 * Pogoplug PHP API
 *
 * This class creates an easy way to interact with the Pogoplug API so you can integrate
 * your Pogoplug device(s) into your website or web application.
 *
 * @category    API
 * @package     pogoplug-php-api
 * @version     0.1  (11-14-2012)
 * @author      David Miles <david@amereservant.com>
 * @copyright   ©2012 David Miles
 * @link        https://github.com/amereservant/pogoplug-php-api
 * @license     http://opensource.org/licenses/mit-license.php MIT License
 * @todo        Add Data Stream API methods and finish adding additional API methods.
 */
class pogoplugAPI
{
   /**
    * API Base URL
    *
    * @var      string
    * @access   public
    * @since    0.1
    */
    public $apiUrl = 'http://service.pogoplug.com/svc/api/';
   /**
    * User Email
    *
    * Required for acquiring a valid token
    *
    * @var      string
    * @access   private
    * @since    0.1
    */
    private $_email;

   /**
    * Password
    *
    * Required for acquiring a valid token
    *
    * @var      string
    * @access   private
    * @since    0.1
    */
    private $_password;

   /**
    * Validation Token
    *
    * Required for making authenticated requests
    *
    * @var      string
    * @access   protected
    * @since    0.1
    */
    protected $valToken;

   /**
    * Response Format
    *
    * Valid options are `xml`, `json`, or `soap`.  This class uses `json`.
    *
    * @var      string
    * @access   public
    * @since    0.1
    */
    public $format='json';

   /**
    * Class Constructor
    *
    * Sets the {@link $_email} and {@link $_password} properties and returns instanceof
    * this class.
    *
    * @param    string  $_username  The Pogoplug email to authenticate with
    * @param    string  $_password  The Pogoplug password to authenticate with
    * @return   object  Instance of this class
    * @access   public
    * @since    0.1
    */
    public function __construct( $_email, $_password )
    {
        $this->_email    = $_email;
        $this->_password = $_password;

        if( isset($_SESSION['valtoken']) )
            $this->valToken = $_SESSION['valtoken'];

        if( strlen($this->valToken) < 1 )
            $this->getToken();
    }

   /**
    * Make API Request
    *
    * This is the primary method for making all API calls.
    *
    * @param    string  $method  The API method name for the request
    * @param    array   $data    An array of params => values to send in the request
    * @return   array            An array of the return data or (bool)false on fail
    * @access   protected
    * @since    0.1
    */
    protected function apiRequest( $method, $data=array() )
    {
        $queryParams = http_build_query($data);
        $queryUrl    = $this->apiUrl . $this->format .'/'. $method .'?'. $queryParams;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $queryUrl);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);

        if( $result === false )
            $this->handleError('API Request failed! '. curl_error($ch), array('query' => $queryUrl, 'method' => $method));
        
        $result = @json_decode($result);
        
        if( isset($result->{'HB-EXCEPTION'}) )
        {
            $ecode = $result->{'HB-EXCEPTION'}->ecode;

            // Try the getting the Token and making the call again ...
            if( $ecode == 606 && $method != 'loginUser' )
            {
                $this->getToken(true);
                $this->apiRequest( $method, $data );
            }
            else {
                $this->handleError('API Request failed! '. $this->apiErrorMsg( $ecode ),
                    array('query' => $queryUrl, 'method' => $method));
            }
        }
        
        return $result;
    }
    
   /**
    * Get Validation Token
    *
    * Attempts to get a validation token based on the {@link $_email} and {@link $_password}
    * properties.
    *
    * @param    bool        $force_new  Force retrieving a new token or not
    * @return   bool        True on success, false if failed
    * @access   protected
    * @since    0.1
    */
    protected function getToken( $force_new=false )
    {
        if( !isset($_SESSION['valtoken']) || $force_new )
        {
            $result = $this->apiRequest('loginUser', 
                array('email' => $this->_email, 'password' => $this->_password)
            );

            if( isset($result->valtoken) )
            {
                $this->valToken       = $result->valtoken;
                $_SESSION['valtoken'] = $this->valToken;
                return true;
            }
        }
            
        return false;
        
    }

   /**
    * Get User Details
    *
    * Fetches the user's details.
    *
    * @param    void
    * @return   object  An object containing the user details or false on fail
    * @access   public
    * @since    0.1
    */
    public function getUser()
    {
        $result = $this->apiRequest('getUser', array('valtoken' => $this->valToken));
        return $result->user;
    }

   /**
    * List Devices
    *
    * Fetches a list of Pogoplug devices associated with the user's account.
    *
    * @param    void
    * @return   array
    * @access   public
    * @since    0.1
    */
    public function listDevices()
    {
        $result = $this->apiRequest('listDevices', array('valtoken' => $this->valToken));
        return $result->devices;
    }

   /**
    * List Services
    *
    * Fetches a list of all the services available to a user grouped either by 'owned' or
    * 'shared'.
    *
    * @param    string  $device_id  The device ID to retrieve services for. (optional)
    * @param    bool    $shared     Whether or not to only list services shared with this user
    *                               or show all services. (optional)
    * @return   object
    * @access   public
    * @since    0.1
    */
    public function listServices( $device_id=null, $shared=null )
    {
        $params = array('valtoken' => $this->valToken);

        if( !is_null($device_id) )
            $params['deviceid'] = $device_id;

        if( !is_null($shared) )
            $params['shared'] = $shared;

        $result = $this->apiRequest('listServices', $params);
        return $result;
    }

   /**
    * List Files
    *
    * Fetch the contents of a directory, namespace, or service in a paginated fashion.
    *
    * @param    string  $deviceid   The device ID to get the file list from.
    * @param    string  $serviceid  The service ID to get the file list from.
    * @param    string  $params     Additional optional parameters as follows:
    * <code>
    *   $params['spaceid']      = string;   // View or Space ID identifying the namespace.
    *   $params['parentid']     = string;   // ID of the parent object to list files within.
    *   $params['pageoffset']   = integer;  // If results are returned in a paginated format, 
    *                                       // this indicates the 0 based index of the page of results to
    *                                       // return.  Each page will return up to the maxcount items.
    *   $params['maxcount']     = integer;  // The maximum number of items to return per request.
    *                                       // (Server may limit it to fewer based on server resources)
    *   $params['searchcrit']   = bool;     // Whether or not to include hidden files in the results.
    *   $params['sortcrit']     = string;   // Sort criteria for the file list.  Valid values are:
    *                                       // '+name, -name, +date, -date, +type, -type, +size, -size'
    * </code>
    * @return   object
    * @access   public
    * @since    0.1
    */
    public function listFiles( $deviceid, $serviceid, $params=array() )
    {
        $params['valtoken'] = $this->valToken;
        $params['deviceid'] = $deviceid;
        $params['serviceid'] = $serviceid;

        $result = $this->apiRequest('listFiles', $params);
        return $result->files;
    }

   /**
    * Search Files
    *
    * Search an entire service based on some criteria.
    *
    * ** NOTE ** This method does NOT work since the documentation is incomplete/vague
    *
    * @param    string  $searchcrit     The search criteria to search by
    * @param    string  $serviceid      The service ID to get the file list from.
    * @param    string  $params         Additional optional parameters as follows:
    * <code>
    *   $params['pageoffset']   = integer;  // If results are returned in a paginated format, 
    *                                       // this indicates the 0 based index of the page of results to
    *                                       // return.  Each page will return up to the maxcount items.
    *   $params['maxcount']     = integer;  // The maximum number of items to return per request.
    *                                       // (Server may limit it to fewer based on server resources)
    *   $params['sortcrit']     = string;   // Sort criteria for the file list.  Valid values are:
    *                                       // '+name, -name, +date, -date, +type, -type, +size, -size'
    * </code>
    * @return   object
    * @access   public
    * @since    0.1
    * @todo     Update method once I figure out the correct input arguments
    */
    public function searchFiles( $searchcrit, $deviceid, $serviceid, $params=array() )
    {
        $params['valtoken']     = $this->valToken;
        $params['serviceid']    = $serviceid;
        $params['deviceid']     = $deviceid;
        $params['searchcrit']   = $searchcrit;
        
        $result = $this->apiRequest('searchFiles', $params);
        return $result;
    }

   /**
    * Get File Data
    *
    * Fetches a file's data structure from a fileid or path.
    * Either a {$fileid} or {$path} must be specified!
    *
    * @param    string  $deviceid   The device ID to get the file data from.
    * @param    string  $serviceid  The service ID to get the file data from.
    * @param    string  $fileid     Fileid to lookup
    * @param    string  $path       Path to lookup
    * @return   object
    * @access   public
    * @since    0.1
    */
    public function getFile( $deviceid, $serviceid, $fileid=null, $path=null )
    {
        $params['valtoken']     = $this->valToken;
        $params['serviceid']    = $serviceid;
        $params['deviceid']     = $deviceid;

        if( !is_null($fileid) )
            $params['fileid'] = $fileid;

        if( !is_null($path) )
            $params['path'] = $path;

        $result = $this->apiRequest('getFile', $params);
        return $result->file;
    }

   /**
    * Create File
    *
    * Creates a new file or directory.
    * This file will be empty and data can be written to it using the data stream API 
    * with the returned fileid.
    *
    * @param    string  $deviceid   The device ID to create the file on.
    * @param    string  $serviceid  The service ID to create the file on.
    * @param    string  $filename   File name of the new file to create.
    * @param    string  $type       Integer file type of file to create:
    *                               0 = Normal File, 1 = Directory, 2 = Extra Stream,
    *                               3 = Symbolic Link
    * @param    string  $spaceid    Name space ID to create file within (Defaults to "DEFAULT")
    * @param    string  $parentid   Fileid of parent directory to create file within
    *                               (defaults to root)
    * @return   string              Fileid on success, false on fail
    * @access   public
    * @since    0.1
    */
    public function createFile( $deviceid, $serviceid, $filename, $type, $spaceid=null, $parentid=null )
    {
        $params['valtoken']     = $this->valToken;
        $params['serviceid']    = $serviceid;
        $params['deviceid']     = $deviceid;
        $params['filename']     = $filename;
        $params['type']         = $type;

        if( !is_null($spaceid) )
            $params['spaceid'] = $spaceid;

        if( !is_null($parentid) )
            $params['parentid'] = $parentid;

        $result = $this->apiRequest('createFile', $params);
        return $result->file;
    }

   /**
    * Remove File
    *
    * Removes the specified file or directory.
    * There is no intermediate Recycle Bin or Trash, it's immediately deleted and destroyed.
    *
    * @param    string  $deviceid   The device ID to delete file from.
    * @param    string  $serviceid  The service ID to delete file from.
    * @param    string  $fileid     Fileid of the file to delete.
    * @param    string  $spaceid    Name space ID to create file within (Defaults to "DEFAULT")
    * @return   void
    * @access   public
    * @since    0.1
    */
    public function removeFile( $deviceid, $serviceid, $fileid, $spaceid=null )
    {
        $params['valtoken']     = $this->valToken;
        $params['serviceid']    = $serviceid;
        $params['deviceid']     = $deviceid;
        $params['fileid']       = $fileid;
        
        if( !is_null($spaceid) )
            $params['spaceid'] = $spaceid;

        $this->apiRequest('removeFile', $params);
        return;
    }
    
   /**
    * API Error Message
    *
    * Returns the API error message that corresponds with the response code.
    *
    * @param    integer $ecode  The error code from the return.
    * @return   string          The error message
    * @access   public
    * @since    0.1
    */
    public function apiErrorMsg( $ecode )
    {
        switch($ecode)
        {
            case 400: // Client Error
                return 'Unspecified client error';
                break;

            case 500: // Server Error
                return 'Unspecified server error';
                break;
                
            case 600: // Invalid Argument
                return 'Invalid argument format or missing required argument';
                break;
                
            case 601: // Out Of Range
                return 'Index into list out of range (e.g. page offset)';
                break;

            case 602: // Not Implemented
                return 'The request cannot be fullfilled because it is not implemented';
                break;

            case 606: // Not Authorized
                return 'The valtoken is not valid or has expired';
                break;

            case 800: // No Such User
                return 'User does not exist';
                break;

            case 801: // No Such Device
                return 'The referenced device does not exist';
                break;

            case 802: // No Such Service
                return 'The referenced service does not exist';
                break;

            case 803: // No Such Space
                return 'The referenced space does not exist';
                break;

            case 804: // No Such File
                return 'The referenced file does not exist';
                break;

            case 805: // Insufficient Permissions
                return 'The user represented by the valtoken does not have permission to do this';
                break;

            case 806: // Not Available
                return 'Generic unavailable error response';
                break;

            default:
                return 'Unknown error';
                break;
        }
    }
    
   /**
    * Handle Errors
    *
    * This method is used to handle all errors and may be changed accordingly to suit
    * your error handling needs.
    *
    * @param    string  $msg    Error message explaining the error
    * @param    array   $details    Any data or additional details to include.  Param => Value pairs.
    * @return   void
    * @since    0.1
    */
    protected function handleError( $msg, $details=array() )
    {
        $format = "%s = %s\n";

        echo $msg .". DETAILS: ";
        foreach($details as $key => $val)
            printf($format, $key, $val);
        exit();
    }
}

