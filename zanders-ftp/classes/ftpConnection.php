<?php

class shalako_FtpConnection
{

	//set up basic class variables

	protected $server;
	protected $username;
	protected $password;
	protected $passive;
	public $port = 21;
	public $timeout = 60;

	public $remotefolder ;
	public $localfolder;
	public $filelist;
	public $conn_id;



 	public function __construct ( $server, $username, $password, $passive, $port, $timeout ) {
 		$this->server = $server;
 		$this->username = $username;
 		$this->password = $password;
 		$this->passive = $passive;
 		$this->port = $port;
 		$this->timeout = $timeout;
 	}


	public function connect()
	{

		$this->conn_id = ftp_connect($this->server, $this->port, $this->timeout); //need to set a timeout here and check if we connected

		//if we cant connect, lets exit
		if($this->conn_id === false):
			return false;
		else:
			$login_status = ftp_login($this->conn_id, $this->username, $this->password);
			if($this->passive == true):
				ftp_pasv($this->conn_id, true);
			else:
				ftp_pasv($this->conn_id, false);
			endif;
			
			return $login_status;
		endif;
	

	}
	public function listdir($folder)
	{
		return ftp_nlist($this->conn_id, $folder);
	}

	public function downloadfile($file)
	{
		//remove the remote directory from the file then add the local directory
		$localfile = str_replace($this->remotefolder, "", $file); 

		// if the local folder does not exist, lets create it
		if(!file_exists($this->localfolder)):
			$createstatus = $this->createdirectory($this->localfolder);
		endif;

		return ftp_get($this->conn_id, $this->localfolder . $localfile, $file, FTP_BINARY);
	}
	public function closeconnection()
	{
		return ftp_close($this->conn_id);
	}
	public function createdirectory($directory)
	{
		return mkdir($directory);
	}


}
