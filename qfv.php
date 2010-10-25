<?php
/**
 * QFV class
 *
 * This source file can be used to communicate with Qualcomm QFV Third Party Interface
 *
 * The class is documented in the file itself. If you find any bugs help me out and report them. Reporting can be done by sending an email to php-qfv-bugs[at]verkoyen[dot]eu.
 * If you report a bug, make sure you give me enough information (include your code).
 *
 * License
 * Copyright (c) 2009, Tijs Verkoyen. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products derived from this software without specific prior written permission.
 *
 * This software is provided by the author "as is" and any express or implied warranties, including, but not limited to, the implied warranties of merchantability and fitness for a particular purpose are disclaimed. In no event shall the author be liable for any direct, indirect, incidental, special, exemplary, or consequential damages (including, but not limited to, procurement of substitute goods or services; loss of use, data, or profits; or business interruption) however caused and on any theory of liability, whether in contract, strict liability, or tort (including negligence or otherwise) arising in any way out of the use of this software, even if advised of the possibility of such damage.
 *
 * @author			Tijs Verkoyen <php-qfv@verkoyen.eu>
 * @version			1.0.0
 *
 * @copyright		Copyright (c) 2009, Tijs Verkoyen. All rights reserved.
 * @license			BSD License
 */
class QFV
{
	// internal constant to enable/disable debugging
	const DEBUG = false;

	// the API url
	const API_URL = 'https://export.fleetvisor.eu/wsTPI/service.svc/rest';

	// the API port
	const API_PORT = 443;

	// current version
	const VERSION = '1.0.0';


	/**
	 * The customer for an authenticating user
	 *
	 * @var	string
	 */
	private $customer;


	/**
	 * The password for an authenticating user
	 *
	 * @var	string
	 */
	private $password;


	/**
	 * The timeout
	 *
	 * @var	int
	 */
	private $timeOut = 60;


	/**
	 * The user agent
	 *
	 * @var	string
	 */
	private $userAgent;


	/**
	 * The username for an authenticating user
	 *
	 * @var	string
	 */
	private $username;


// class methods
	/**
	 * Default constructor
	 *
	 * @return	void
	 * @param	string[optional] $customer
	 * @param	string[optional] $username
	 * @param	string[optional] $password
	 */
	public function __construct($customer = null, $username = null, $password = null)
	{
		if($customer !== null) $this->setCustomer($customer);
		if($username !== null) $this->setUsername($username);
		if($password !== null) $this->setPassword($password);
	}


	/**
	 * Make the call
	 *
	 * @return	string
	 * @param	string $url
	 * @param	array[optiona] $aParameters
	 * @param	bool[optional] $authenticate
	 * @param	bool[optional] $usePost
	 */
	private function doCall($url, $aParameters = array())
	{
		// redefine
		$url = (string) $url;
		$aParameters = (array) $aParameters;

		// build url
		$url = self::API_URL .'/'. $url;

		// validate needed authentication
		if($this->getCustomer() == '' || $this->getUsername() == '' || $this->getPassword() == '') throw new QFVException('No customer, username or password was set.');

		// just sleep
		usleep(200);

		// add parameters
		$aParameters['customer'] = $this->getCustomer();
		$aParameters['username'] = $this->getUsername();
		$aParameters['password'] = $this->getPassword();

		// rebuild url if we don't use post
		if(!empty($aParameters))
		{
			// init var
			$queryString = '';

			// loop parameters and add them to the queryString
			foreach($aParameters as $key => $value) $queryString .= '&'. $key .'='. urlencode(utf8_encode($value));

			// cleanup querystring
			$queryString = trim($queryString, '&');

			// append to url
			$url .= '?'. $queryString;
		}

		// set options
		$options[CURLOPT_URL] = $url;
		$options[CURLOPT_PORT] = self::API_PORT;
		$options[CURLOPT_USERAGENT] = $this->getUserAgent();
		$options[CURLOPT_FOLLOWLOCATION] = true;
		$options[CURLOPT_RETURNTRANSFER] = true;
		$options[CURLOPT_TIMEOUT] = (int) $this->getTimeOut();
		$options[CURLOPT_SSL_VERIFYPEER] = false;
		$options[CURLOPT_SSL_VERIFYHOST] = false;

		// init
		$curl = curl_init();

		// set options
		curl_setopt_array($curl, $options);

		// execute
		$response = curl_exec($curl);
		$headers = curl_getinfo($curl);

		// fetch errors
		$errorNumber = curl_errno($curl);
		$errorMessage = curl_error($curl);

		// close
		curl_close($curl);

		// validate body
		$xml = @simplexml_load_string($response);

		// validate XML
		if($xml === false) throw new QFVException('Invalid response');
		if($xml !== false && $xml->getName() == 'Fault')
		{
			$code = null;
			$message = '';

			// get data
			if(isset($xml->Code->Subcode->Value)) $code = (int) $xml->Code->Subcode->Value;
			if(isset($xml->Code->Value)) $message .= (string) $xml->Code->Value .': ';
			if(isset($xml->Reason->Text)) $message .= (string) $xml->Reason->Text;
			if($message == '') $message = 'Unknown error';

			// if we are debugging show more ing
			if(self::DEBUG)
			{
				echo '<pre>';
				var_dump($headers);
				var_dump($response);
				echo '</pre>';
				exit;
			}

			// throw exception
			throw new QFVException($message, $code);
		}

		// invalid headers
		if(!in_array($headers['http_code'], array(0, 200)))
		{
			// should we provide debug information
			if(self::DEBUG)
			{
				echo '<pre>';
				var_dump($headers);
				var_dump($response);
				echo '</pre>';
				exit;
			}

			// throw error
			throw new QFVException(null, (int) $headers['http_code']);
		}

		// error?
		if($errorNumber != '') throw new QFVException($errorMessage, $errorNumber);

		// return
		return $xml;
	}


	/**
	 * Get the customer
	 *
	 * @return	string
	 */
	private function getCustomer()
	{
		return (string) $this->customer;
	}


	/**
	 * Get the password
	 *
	 * @return	string
	 */
	private function getPassword()
	{
		return (string) $this->password;
	}


	/**
	 * Get the timeout
	 *
	 * @return	int
	 */
	public function getTimeOut()
	{
		return (int) $this->timeOut;
	}


	/**
	 * Get the useragent
	 *
	 * @return	string
	 */
	public function getUserAgent()
	{
		return (string) 'PHP QFV/'. self::VERSION .' '. $this->userAgent;
	}


	/**
	 * Get the username
	 *
	 * @return	string
	 */
	private function getUsername()
	{
		return (string) $this->username;
	}


	/**
	 * Set the customer
	 *
	 * @return	void
	 * @param	string $customer
	 */
	private function setCustomer($customer)
	{
		$this->customer = (string) $customer;
	}


	/**
	 * Set password
	 *
	 * @return	void
	 * @param	string $password
	 */
	private function setPassword($password)
	{
		$this->password = (string) $password;
	}


	/**
	 * Set the timeout
	 *
	 * @return	void
	 * @param	int $seconds
	 */
	public function setTimeOut($seconds)
	{
		$this->timeOut = (int) $seconds;
	}


	/**
	 * Set the user-agent for you application
	 * It will be appended to ours
	 *
	 * @return	void
	 * @param	string $userAgent
	 */
	public function setUserAgent($userAgent)
	{
		$this->userAgent = (string) $userAgent;
	}


	/**
	 * Set username
	 *
	 * @return	void
	 * @param	string $username
	 */
	private function setUsername($username)
	{
		$this->username = (string) $username;
	}


// subscription methods
	/**
	 * The method creates a new data subscription for the specified user. Only one subscription for a specific data
	 * type will be allowed. Attempts to create more subscriptions for the same data and under the same user
	 * account will be ignored.
	 * Using single-request subscriptions is highly recommended in the test phase, when client application is still
	 * being developed, so there's still no need for continuous data inflow from QFV.
	 *
	 * @return	int					Identifier of a newly created subscription
	 * @param	string $dataType	Type of data to subscribe to, possible values: Positions, Messages, MessageStatusUpdates, DriverEvents, DriverTotals, TrailerEvents, ETAEvents
	 * @param	string $description	User-friendly description of a subscription
	 * @param	string[optional] $subscriptionType	Type of subscription, possible values: Regular, SingleRequest
	 */
	public function addSubscription($dataType, $description, $subscriptionType = 'Regular')
	{
		// possible values
		$dataTypes = array('Positions', 'Messages', 'MessageStatusUpdates', 'DriverEvents', 'DriverTotals', 'TrailerEvents', 'ETAEvents');
		$subscriptionTypes = array('Regular', 'SingleRequest');

		// redefine
		$dataType = (string) $dataType;
		$description = (string) $dataType;
		$subscriptionType = (string) $subscriptionType;

		// validate
		if(!in_array($dataType, $dataTypes)) throw new QFVException('Invalid dataType.');
		if(!in_array($subscriptionType, $subscriptionTypes)) throw new QFVException('Invalid subscriptionType.');

		// init vars
		$aParameters['subscriptiontype'] = $subscriptionType;
		$aParameters['datatype'] = $dataType;
		$aParameters['description'] = $description;

		// make the call
		$response = $this->doCall('addSubscription', $aParameters);

		// return
		return (int) $response->int;
	}


	/**
	 * To learn what subscriptions a specific user has created, one can use GetSubscriptions method of TPI Service.
	 *
	 * @return	array
	 */
	public function getSubscriptions()
	{
		// make the call
		$response = $this->doCall('GetSubscriptions', array());

		// init
		$subscriptions = array();

		// loop subscriptions
		foreach($response->Subscription as $row)
		{
			// build array
			$temp = array();
			$temp['cid'] = (int) $row->CID;
			$temp['created'] = (int) strtotime((string) $row->Created);
			$temp['data_type'] = utf8_decode((string) $row->DataType);
			$temp['description'] = utf8_decode((string) $row->Description);
			$temp['filter'] = (isset($row->Filter)) ? utf8_decode((string) $row->Filter) : null;
			$temp['id'] = (int) $row->Id;
			$temp['is_enabled'] = (bool) ((string) $row->IsEnabled == 'true');
			$temp['subscription_type'] = utf8_decode((string) $row->SubscriptionType);
			$temp['username'] = (isset($row->UserName)) ? utf8_decode((string) $row->UserName) : null;

			// add
			$subscriptions[] = $temp;
		}

		// return
		return (array) $subscriptions;
	}


	/**
	 * To stop a subscription, one needs to know the subscription identifier, and call DeleteSubscription method of the TPI service
	 * REMARK: this method is untested
	 *
	 * @return	bool	true on success
	 * @param	int $id	Identifier of a subscription to delete
	 */
	public function stopSubscription($id)
	{
		// init vars
		$aParameters['Id'] = (int) $id;

		// make the call
		$response = $this->doCall('deleteSubscription', $aParameters);

		// success
		if((int) $response == 1) return true;

		// fallback
		return false;
	}


// mailbox methods
	/**
	 * Before actual retrieving the subscribed data, one can poll the TPI service to obtain information whether there are any data to be retrieved.
	 *
	 * @return	array
	 */
	public function getMailboxInfo()
	{
		// get response
		$response = $this->doCall('GetMailboxInfo', array());

		$aReturn = array();
		$aReturn['cid'] = (string) $response->CID;
		$aReturn['count'] = (int) $response->Count;
		$aReturn['id'] = (int) $response->Id;
		$aReturn['max_packet'] = (int) $response->MaxPacket;
		$aReturn['min_packet'] = (int) $response->MinPacket;
		$aReturn['size'] = (int) $response->Size;
		$aReturn['username'] = (string) $response->UserName;

		// return
		return $aReturn;
	}


	/**
	 * The method returns all data that the specified user has subscribed, which has been registered in the system
	 * since the previous call. All data is returned in one XML document, grouped per data type. After the data is
	 * retrieved and returned, it is marked for removal from user's mailbox, unless markasread parameter has been
	 * specified as false. Once marked for removal, this can happen at any moment, so there's no further guarantee
	 * that this specific data will be available any more, and there is no way to retrieve it again.
	 * REMARK: Not fully tested.
	 *
	 * @return	array
	 * @param	bool[optional] $markAsRead	Specifies whether retrieved data should be marked as read, so once retrieved, it will be removed from the mailbox
	 * @param	int[optional] $maxCount		Maximal number of records to return. When greater than zero, only the specified number of the newest packets will be returned, while the rest will be just discarded. When zero specified, all packets in the mailbox will be returned.
	 */
	public function retrieve($markAsRead = true, $maxCount = 0)
	{
		// init vars
		$aParameters = array();
		$aParameters['markasread'] = ((bool) $markAsRead) ? 'true' : 'false';
		$aParameters['maxcount'] = (int) $maxCount;

		// get response
		$response = $this->doCall('retrieve', $aParameters);

		// init var
		$aReturn = array();

		// summary
		$aReturn['user'] = (string) $response['user'];
		$aReturn['date_executed'] = (int) strtotime((string) $response['datetime']);
		$aReturn['summary']['request_interval'] = (int) $response->Summary->RequestInterval;
		$aReturn['summary']['packet_count'] = (int) $response->Summary->PacketCount;
		$aReturn['summary']['min_packet'] = (int) $response->Summary->MinPacket;
		$aReturn['summary']['max_packet'] = (int) $response->Summary->MaxPacket;
		$aReturn['summary']['processing_time'] = (int) $response->Summary->ProcessingTime;

		// messages
		if(isset($response->Messages))
		{
			// init
			$aReturn['messages'] = array();

			// loop messages
			foreach($response->Messages->Msg as $row)
			{
				// build array
				$temp = array();
				$temp['packet_id'] = $row['PacketId'];
				$temp['pos_id'] = $row['PosId'];
				$temp['user_message_id'] = $row['PacketId'];
				$temp['created'] = (int) strtotime((string) $row->CreationDT);
				$temp['msisdn'] = (string) $row->MSISDN;
				$temp['copy'] = (bool) ((string) $row->Copy == 'true');
				$temp['class'] = (string) $row->Class;
				$temp['app_id'] = (string) $row->AppId;
				$temp['type'] = (string) $row->Type;
				$temp['gmh'] = (int) $row->GMH;
				$temp['priority'] = (int) $row->Priority;
				$temp['req_read_receipt'] = (bool) ((string) $row->ReqRR == 'true');
				$temp['status_date'] = (int) strtotime((string) $row->StatusDT);
				$temp['status'] = (string) $row->Status;
				$temp['transmit_date'] = (int) strtotime((string) $row->TxDT);
				$temp['author'] = utf8_decode((string) $row->Author);
				$temp['data'] = (string) $row->Data;

				// add
				$aReturn['messages'][] = $temp;
			}
		}

		// positions
		if(isset($response->Positions->Pos))
		{
			// init
			$aReturn['positions'] = array();

			// loop positions
			foreach($response->Positions->Pos as $row)
			{
				// build array
				$temp = array();
				$temp['packet_id'] = (string) $row['PacketId'];
				$temp['pos_id'] = (string) $row->PosId;
				$temp['msisdn'] = (string) $row->MSISDN;
				$temp['dt'] = (int) strtotime($row->DT);
				$temp['ignition'] = (bool) ((string) $row->Ignition == 1);
				$temp['pos'] = array();
				$temp['pos']['lat'] = (string) $row->POS['lat'];
				$temp['pos']['lon'] = (string) $row->POS['lon'];
				$temp['pos']['country'] = utf8_decode((string) $row->POS->Country);
				$temp['pos']['street']['name'] = utf8_decode((string) $row->POS->Street);
				$temp['pos']['street']['nr'] = utf8_decode((string) $row->POS->Street['nr']);
				$temp['pos']['street']['postalcode'] = utf8_decode((string) $row->POS->Street['postalcode']);
				$temp['pos']['city'] = utf8_decode((string) $row->POS->City);
				$temp['pos']['nearest_city'] = utf8_decode((string) $row->POS->NearestCity);

				// add
				$aReturn['positions'][] = $temp;
			}
		}

		// message status updates
		if(isset($response->MessageStatusUpdates))
		{
			// init
			$aReturn['message_status_updates'] = array();

			// loop positions
			foreach($response->MessageStatusUpdates->MsgStatus as $row)
			{
				// build array
				$temp = array();
				$temp['packet_id'] = (int) $row['PacketId'];
				$temp['user_msg_id'] = (int) $row->UserMsgID;
				$temp['date'] = (int) strtotime((string) $row->DT);
				$temp['status'] = (string) $row->Status;

				// add
				$aReturn['message_status_updates'][] = $temp;
			}
		}

		// driver events
		if(isset($response->DriverEvents))
		{
			// init
			$aReturn['driver_events'] = array();

			// loop positions
			foreach($response->DriverEvents->Event as $row)
			{
				// build array
				$temp = array();
				$temp['packet_id'] = (int) $row['PacketId'];
				$temp['pos_id'] = (isset($row['PosId'])) ? (int) $row['PosId'] : null;
				$temp['entry_id'] = (int) $row->EntryId;
				$temp['date'] = (int) strtotime((string) $row->DT);
				$temp['card_id'] = (int) $row->CardId;
				$temp['is_co'] = (bool) ((string) $row->IsCo == 'true');
				$temp['co_card_id'] = (isset($row->CoCardId)) ? (int) $row->CoCardId : null;
				$temp['msisdn'] = (string) $row->MSISDN;
				$temp['status'] = (bool) ((string) $row->Status == 'true');
				$temp['activity'] = (int) $row->Activity;
				$temp['segment_activity'] = (int) $row->SegmentActivity;
				$temp['segment_status'] = (bool) ((string) $row->SegmentStatus == 'true');
				$temp['segment_duration'] = (int) $row->SegmentDuration;
				$temp['segment_delta'] = (int) $row->SegmentDelta;
				$temp['sub_activity'] = (int) $row->SubActivity;
				$temp['segment_sub_activity'] = (int) $row->SegmentSubActivity;
				$temp['odometer'] = (int) $row->OdoMeter;
				$temp['msg_seq'] = (int) $row->MsgSeq;
				$temp['fields'] = null;

				foreach($row->Fields->Field as $field)
				{
					$field = array();
					$field['type'] = (int) $field['type'];
					$field['value'] = (string) $field['value'];

					$temp['fields'][] = $field;
				}

				// add
				$aReturn['driver_events'][] = $temp;
			}
		}

		// driver totals
		if(isset($response->DriverTotals))
		{
			// init
			$aReturn['driver_totals'] = array();

			// loop positions
			foreach($response->DriverTotals->DriverTotals as $row)
			{
				// build array
				$temp = array();
				$temp['packet_id'] = (int) $row['PacketId'];
				$temp['hours']['card_id'] = (int) $row->Hours->CardId;
				$temp['hours']['is_co'] = (bool) ((string) $row->Hours->IsCo == 'true');
				$temp['hours']['card_status'] = (string) $row->Hours->CardStatus;
				$temp['hours']['msisdn'] = (string) $row->Hours->MSISDN;
				$temp['hours']['vehicle'] = (string) $row->Hours->Vehicle;
				$temp['hours']['on_duty'] = (string) $row->Hours->OnDuty;
				$temp['hours']['last_event'] = (string) $row->Hours->LastEvent;
				$temp['hours']['activity'] = (string) $row->Hours->Activity;
				$temp['hours']['duration'] = (int) $row->Hours->Duration;
				$temp['hours']['start_trip'] = (string) $row->Hours->StartTrip;
				$temp['hours']['extended_driving'] = (string) $row->Hours->ExtendedDriving;
				$temp['hours']['week_drive'] = (string) $row->Hours->WeekDrive;
				$temp['hours']['month_drive'] = (string) $row->Hours->MonthDrive;
				$temp['hours']['week_duty'] = (string) $row->Hours->WeekDuty;
				$temp['hours']['week_labour'] = (string) $row->Hours->WeekLabour;
				$temp['hours']['month_duty'] = (string) $row->Hours->MonthDuty;
				$temp['hours']['month_effectivity'] = (string) $row->Hours->MonthEffectivity;
				$temp['hours']['start_op_week'] = (string) $row->Hours->StartOpWeek;
				$temp['hours']['prev_op_week_rest'] = (string) $row->Hours->PrevOpWeekRest;

				// add
				$aReturn['driver_totals'][] = $temp;
			}
		}

		// trailer events
		if(isset($response->TrailerEvents))
		{
			// init
			$aReturn['trailer_events'] = array();

			// loop positions
			foreach($response->TrailerEvents->TrailerEvent as $row)
			{
				// build array
				$temp = array();
				$temp['packet_id'] = (int) $row['PacketId'];
				$temp['pos_id'] = (isset($row['PosId'])) ? (int) $row['PosId'] : null;
				$temp['entry_id'] = (int) $row->EntryId;
				$temp['trailer_id'] = (int) $row->TrailerId;
				$temp['msisdn'] = (string) $row->MSISDN;
				$temp['trailer_type'] = (string) $row->TrailerType;
				$temp['date'] = (int) strtotime((string) $row->DT);
				$temp['event'] = (int) $row->Event;
				$temp['reefer_mode'] = (string) $row->ReeferMode;
				$temp['reefer_alarms'] = (int) $row->ReeferAlarms;
				$temp['supply_temperature'] = (int) $row->SupplyTemperature;
				$temp['return_temperature'] = (int) $row->ReturnTemperature;
				$temp['setpoint_temperature'] = (int) $row->SetpointTemperature;

				// add
				$aReturn['trailer_events'][] = $temp;
			}
		}

		// ETA events
		if(isset($response->ETAEvents))
		{
			// init
			$aReturn['eta_events'] = array();

			// loop positions
			foreach($response->ETAEvents->ETA as $row)
			{
				// build array
				$temp = array();
				$temp['packet_id'] = (int) $row['PacketId'];
				$temp['pos_id'] = (isset($row['PosId'])) ? (int) $row['PosId'] : null;
				$temp['entry_id'] = (int) $row->EntryId;
				$temp['msisdn'] = (string) $row->MSISDN;
				$temp['date'] = (int) strtotime((string) $row->DT);
				$temp['job_id'] = (int) $row->JobId;
				$temp['card_id'] = (int) $row->CardId;
				$temp['co_card_id'] = (int) $row->CoCardId;
				$temp['category'] = (int) $row->Category;
				$temp['event'] = (int) $row->Event;
				$temp['status'] = (int) $row->Status;
				$temp['eta'] = (int) strtotime((string) $row->ETA);
				$temp['distance_to_poi'] = (int) $row->DistanceToPOI;
				$temp['bearing_to_poi'] = (int) $row->BearingToPOI;
				$temp['poi'] = utf8_decode((string) $row->POI);

				// add
				$aReturn['eta_events'][] = $temp;
			}
		}

		// return
		return $aReturn;
	}


	/**
	 * The method will mark all specified data packets in user's mailbox as ready for removal.
	 * From this moment packets are not available any more.
	 *
	 * @return	bool				true on success
	 * @param	int $minPacketId	Packet identifier, start of a range of packets to delete
	 * @param	int $maxPacketId	Packet identifier, end of a range of packets to delete
	 */
	public function purge($minPacketId, $maxPacketId)
	{
		// init vars
		$url = 'purge';
		$aParameters['minpacketid'] = (int) $maxPacketId;
		$aParameters['maxpacketid'] = (int) $maxPacketId;

		// make the call
		$response = $this->doCall($url, $aParameters);

		// success
		if((int) $response == 1) return true;

		// fallback
		return false;
	}


// messaging methods
	/**
	 * The method submits a text message, which will be then sent to the specified recipients, and returns an
	 * identifier of the message. Because messages are sent asynchronously, there is no guarantee that the
	 * message has actually been sent at return from this method - the only thing that is sure is that the message
	 * has been registered in the system and waits for further processing. If proper message delivery is of concern,
	 * the returned message identifier can be used to track message status.
	 * REMARK: Not implemented at this moment.
	 *
	 *
	 * @return	int							Unique identifier of the newly created message
	 * @param	array $recipients			An array with the MSISDN identifiers of vehicles which should recieve the message
	 * @param	int $sendAfter				UNIX-timestamp after which the message should be dispatched to the recipients
	 * @param	bool $requireReadReceipt	When true, read receipt will be requested for the sent message
	 * @param	string $text				Message text
	 */
	public function sendTextMessage($recipients, $sendAfter, $requireReadReceipt, $text)
	{
		throw new QFVException('Not Implemented');
	}


	/**
	 * The method submits a form message, which will be then sent to the specified recipients, and returns an
	 * identifier of the message. Because messages are sent asynchronously, there is no guarantee that the
	 * message has actually been sent at return from this method - the only thing that is sure is that the message
	 * has been registered in the system and waits for further processing. If proper message delivery is of concern,
	 * the returned message identifier can be used to track message status.
	 * REMARK: Not implemented at this moment.
	 *
	 * @return	int							Unique identifier of the newly created message
	 * @param	array $recipients			An array with the MSISDN identifiers of vehicles which should recieve the message
	 * @param	int $sendAfter				UNIX-timestamp after which the message should be dispatched to the recipients
	 * @param	bool $requireReadReceipt	When true, read receipt will be requested for the sent message
	 * @param	int $formNumber				Form definition number
	 * @param	int $formVersion			Form version number
	 * @param	array $values				An array of form field values, in order of their appearance in the form definition
	 * @param	string[optional] $separator	Delimiter for form values
	 */
	public function sendFormMessage($recipients, $sendAfter, $requireReadReceipt, $formNumber, $formVersion, $values, $separator = '|')
	{
		throw new QFVException('Not Implemented');
	}


	/**
	 * The method submits a position poll message, which will be then sent to the specified recipients, and returns an
	 * identifier of the message. Because messages are sent asynchronously, there is no guarantee that the
	 * message has actually been sent at return from this method - the only thing that is sure is that the message
	 * has been registered in the system and waits for further processing. If proper message delivery is of concern,
	 * the returned message identifier can be used to track message status.
	 * REMARK: Not implemented at this moment.
	 *
	 * @return	int					Unique identifier of the newly created message
	 * @param	array $recipients	An array with the MSISDN identifiers of vehicles which should recieve the message
	 * @param	int $sendAfter		UNIX-timestamp after which the message should be dispatched to the recipients
	 */
	public function sendPositionPoll($recipients, $sendAfter)
	{
		throw new QFVException('Not Implemented');
	}


	/**
	 * To obtain form definitions, one needs to call GetFormDefinitions method of the TPI service.
	 * REMARK: Not implemented at this moment.
	 *
	 * @return	array
	 */
	public function getFormDefinitions()
	{
		throw new QFVException('Not Implemented');
	}


// asset management methods
	/**
	 * Information about depots can be retrieved by GetDepots method of the TPI service. This information will be needed for
	 * other operations, such as adding assets. Whenever we need to assign a newly created asset to some depot, we need to
	 * specify an identifier of this depot, and this can be found in the dataset returned by GetDepots method.
	 *
	 * @return	array
	 */
	public function getDepots()
	{
		// get response
		$response = $this->doCall('GetDepots', array());

		// init var
		$aReturn = array();

		// loop depots
		foreach($response->Depot as $row)
		{
			// build array
			$temp = array();
			$temp['id'] = (int) $row->Id;
			$temp['name'] = utf8_decode((string) $row->Name);
			$temp['timezone'] = (int) $row->TimeZone;

			// add
			$aReturn[] = $temp;
		}

		// return
		return $aReturn;
	}


	/**
	 * To retrieve a list of all vehicles, please use GetVehicles method.
	 *
	 * @return	array
	 */
	public function getVehicles()
	{
		// get response
		$response = $this->doCall('GetVehicles', array());

		// init var
		$aReturn = array();

		// loop vehicles
		foreach($response->Vehicle as $row)
		{
			// build array
			$temp = array();
			$temp['cid'] = (int) $row->CID;
			$temp['msisdn'] = (string) $row->MSISDN;
			$temp['enabled'] = (bool) ((string) $row->Enabled == 'true');
			$temp['device_type'] = (int) $row->DeviceType;
			$temp['network_id'] = (int) $row->NetworkId;
			$temp['alias'] = (isset($row->Alias)) ? utf8_decode((string) $row->Alias) : null;
			$temp['depot_id'] = (isset($row->DepotId)) ? (int) $row->DepotId : null;
			$temp['unit_id'] = (isset($row->UnitId)) ? (string) $row->UnitId : null;

			// add
			$aReturn[] = $temp;
		}

		return $aReturn;
	}


	/**
	 * To retrieve a list of all drivers, please use GetDrivers method
	 *
	 * @return	array
	 */
	public function getDrivers()
	{
		// get response
		$response = $this->doCall('GetDrivers', array());

		// init var
		$return = array();

		// loop depots
		foreach($response->Driver as $row)
		{
			// build array
			$temp = array();
			$temp['cid'] = (int) $row->Id;
			$temp['card_id'] = (int) $row->CardId;
			$temp['alias'] = (isset($row->Alias)) ? utf8_decode((string) $row->Alias) : null;
			$temp['first_name'] = (isset($row->FirstName)) ? utf8_decode((string) $row->FirstName): null;
			$temp['last_name'] = (isset($row->LastName)) ? utf8_decode((string) $row->LastName): null;
			$temp['depot_id'] = (isset($row->DepotId)) ? (int) $row->DepotId : null;

			// add
			$return[] = $temp;
		}

		// return
		return $return;
	}


	/**
	 * To retrieve binary drivercard images, please use GetDrivercards method.
	 * REMARK: this method is untested.
	 *
	 * @return	array
	 * @param	int $from		UNIX-timestamp for the period start
	 * @param	int $until		UNIX-timestamp for the period end
	 * @param	string $status	Status of the cards to download, possible values: New, Exported, All
	 */
	public function getDrivercards($from, $until, $status = 'All')
	{
		// init var
		$aPossibleStatuses = array('New', 'Exported', 'All');

		// redefine
		$from = (int) $from;
		$until = (int) $until;
		$status = (string) $status;

		// validate
		if(!in_array($status, $aPossibleStatuses)) throw new QFVException('Invalid status.');

		// build parameters
		$aParameters = array();
		$aParameters['from'] = date('c', $from);
		$aParameters['until'] = date('c', $until);
		$aParameters['status'] = $status;

		// get response
		$response = $this->doCall('GetDrivercards', $aParameters);

		// init var
		$return = array();

		// loop depots
		foreach($response->DriverCard as $row)
		{
			// build array
			$temp = array();
			$temp['cid'] = (int) $row->Id;
			$temp['unique_id'] = (string) $row->UniqueID;
			$temp['msisdn'] = (string) $row->MSISDN;
			$temp['driver_id'] = (int) $row->DriverId;
			$temp['depot_id'] = (isset($row->DepotId)) ? (int) $row->DepotId : null;
			$temp['card_nr'] = (isset($row->CardNr)) ? (string) $row->Cardnr : null;
			$temp['card_country'] = (isset($row->CardCountry)) ? (string) $row->CardCountry : null;
			$temp['first_name'] = (isset($row->FirstName)) ? utf8_decode((string) $row->FirstName) : null;
			$temp['last_name'] = (isset($row->LastName)) ? utf8_decode((string) $row->LastName) : null;
			$temp['card_image'] = (isset($row->CardImage)) ? base64_decode((string) $row->CardImage) : null;
			$temp['status'] = (isset($row->Status)) ? (string) $row->Status : null;
			$temp['status_date'] = (isset($row->StatusDT)) ? (int) strtotime((string) $row->StatusDate) : null;
			$temp['device_type'] = (isset($row->DeviceType)) ? (int) $row->DeviceType : null;
			$temp['upload_date'] = (isset($row->UploadDate)) ? (int) strtotime((string) $row->UploadDate) : null;
			$temp['export_date'] = (isset($row->ExportDate)) ? (int) strtotime((string) $row->ExportDate) : null;
			$temp['alias'] = (isset($row->Alias)) ? utf8_decode((string) $row->Alias) : null;
			$temp['template_name'] = (isset($row->TemplateName)) ? utf8_decode((string) $row->TemplateName) : null;
			$temp['last_activity'] = (isset($row->LastActivity)) ? (int) strtotime((string) $row->LastActivity) : null;

			// add
			$return[] = $temp;
		}

		// return
		return $return;
	}


	/**
	 * To retrieve a list of all trailers, please use GetTrailers method
	 *
	 * @return	array
	 */
	public function getTrailers()
	{
		// get response
		$response = $this->doCall('GetTrailers', array());

		// init var
		$aReturn = array();

		// loop vehicles
		foreach($response->Trailer as $row)
		{
			// build array
			$temp = array();
			$temp['cid'] = (int) $row->CID;
			$temp['trailer_id'] = (int) $row->TrailerId;
			$temp['alias'] = (isset($row->Alias)) ? utf8_decode((string) $row->Alias) : null;
			$temp['depot_id'] = (isset($row->DepotId)) ? (int) $row->DepotId : null;
			$temp['type'] = (isset($row->Type)) ? (int) $row->Type : null;

			// add
			$aReturn[] = $temp;
		}

		return $aReturn;
	}


	/**
	 * To add a vehicle, please use AddVehicle method
	 * REMARK: Not tested, 404 as result.
	 *
	 * @return	bool						true on success
	 * @param	string $msisdn				Vehicle identifier
	 * @param	int $deviceType				Device type specification, possible values: 0: MCT, Eutelsat, 1: OXE GSM, 2: O1 TIS GSM, 3: OBU iveco GSM, 4: OV2 GSM/Eutelsat
	 * @param	int $networkId				Network identifier, possible values: 0: EUTELSAT, 1: GSM
	 * @param	int $depot					Depot identifier, if no depot should be assigned specify -1 as a value
	 * @param	string[optional] $alias		Vehicle alias
	 * @param	string[optional] $unitId	Unit identifier
	 */
	public function addVehicle($msisdn, $deviceType, $networkId, $depot, $alias = null, $unitId = null)
	{
		// possible values
		$aPossibleDeviceTypes = array(0, 1, 2, 3, 4);
		$aPossibleNetworkIds = array(0, 1);

		// redefine
		$msisdn = (string) $msisdn;
		$devicetype = (int) $deviceType;
		$networkId = (int) $networkId;
		$depot = (int) $depot;
		$alias = ($alias === null) ? null : (string) $alias;
		$unitId = ($unitId === null) ? null : (string) $unitId;

		// validate
		if(!in_array($deviceType, $aPossibleDeviceTypes)) throw new QFVException('Invalid device type.');
		if(!in_array($networkId, $aPossibleNetworkIds)) throw new QFVException('Invalid networkid.');

		// build parameters
		$aParameters['msisdn'] = $msisdn;
		$aParameters['devicetype'] = $deviceType;
		$aParameters['networkid'] = $networkId;
		$aParameters['depot'] = $depot;
		if($alias !== null) $aParameters['alias'] = $alias;
		if($unitId !== null)  $aParameters['unitid'] = $unitId;

		// get response
		$response = $this->doCall('AddVehicle', $aParameters);

		// success
		if((int) $response == 1) return true;

		// fallback
		return false;
	}


	/**
	 * To add a driver, please use AddDriver method
	 *
	 * @return	bool						true on success
	 * @param	string $cardId				Driver identifier
	 * @param	int $depot					Depot identifier, if no depot should be assigned specify -1 as a value
	 * @param	string[optional] $alias		Driver alias
	 * @param	string[optional] $firstname	Driver first name
	 * @param	string[optional] $lastname	Driver last name
	 */
	public function addDriver($cardId, $depot, $alias = null, $firstname = null, $lastname = null)
	{
		// redefine
		$cardId = (string) $cardId;
		$depot = (int) $depot;
		$alias = ($alias === null) ? null : (string) $alias;
		$firstname = ($firstname === null) ? null : (string) $firstname;
		$lastname = ($lastname === null) ? null : (string) $lastname;

		// build parameters
		$aParameters['cardid'] = $cardId;
		$aParameters['depot'] = $depot;
		if($alias !== null) $aParameters['alias'] = $alias;
		if($firstname !== null) $aParameters['firstname'] = $firstname;
		if($lastname !== null) $aParameters['lastname'] = $lastname;

		// get response
		$response = $this->doCall('AddDriver', $aParameters);

		// success
		if((int) $response == 1) return true;

		// fallback
		return false;
	}


	/**
	 * To add a trailer, please use AddTrailer method
	 * REMARK: untested, result is an error.
	 *
	 * @return	bool				true on success
	 * @param	string $trailerid
	 * @param	int $depot			Depot identifier, if no depot should be assigned specify -1 as a value
	 * @param	string $alias		Trailer alias
	 * @param	string $unitid		Unit identifier
	 * @param	int $trailerType	Trailer type identifier, possible values: 0: unknown, 1: trailer, 2: generic, 3: carrier, 4: thermoking
	 */
	public function addTrailer($trailerid, $depot, $alias, $unitid = null, $trailerType)
	{
		// possible values
		$aPossibleTrailerTypes = array(0, 1, 2, 3, 4);

		// redefine
		$trailerid = (string) $trailerid;
		$depot = (int) $depot;
		$alias = ($alias === null) ? null : (string) $alias;
		$unitid = ($unitid === null) ? null : (string) $unitid;
		$trailerType = (int) $trailerType;

		// validate
		if(!in_array($trailerType, $aPossibleTrailerTypes)) throw new QFVException('Invalid trailer type.');

		// build parameters
		$aParameters['trailerid'] = $trailerid;
		$aParameters['depot'] = $depot;
		if($alias !== null) $aParameters['alias'] = $alias;
		if($unitid !== null) $aParameters['unitid'] = $unitid;
		$aParameters['trailertype'] = $trailerType;

		// get response
		$response = $this->doCall('AddTrailer', $aParameters);

		// success
		if((int) $response == 1) return true;

		// fallback
		return false;
	}


	/**
	 * To modify a vehicle, please use ModifyVehicle method
	 * REMARK: Not implemented at this moment.
	 *
	 * @return	bool			true on success
	 * @param	string $msisdn	Vehicle identifier
	 * @param	int $depot		Depot identifier, if no depot should be assigned specify -1 as a value
	 * @param	string $alias	Vehicle alias
	 * @param	string $unitid	Unit identifier
	 */
	public function modifyVehicle($msisdn, $depot, $alias, $unitid)
	{
		throw new QFVException('Not Implemented');
	}


	/**
	 * To modify a driver, please use ModifyDriver method
	 * REMARK: Not implemented at this moment.
	 *
	 * @return	bool				true on success
	 * @param	string $cardid		Driver identifier
	 * @param	int $depot			Depot identifier, if no depot should be assigned specify -1 as a value
	 * @param	string $alias		Driver alias
	 * @param	string $firstname	Driver first name
	 * @param	string $lastname	Driver last name
	 */
	public function modifyDriver($cardid, $depot, $alias, $firstname, $lastname)
	{
		throw new QFVException('Not Implemented');
	}


	/**
	 * To modify a trailer, please use ModifyTrailer method
	 * REMARK: Not implemented at this moment.
	 *
	 * @return	bool				true on success
	 * @param	string $trailerid	Trailer identifier
	 * @param	int $depot			Depot identifier, if no depot should be assigned specify -1 as a value
	 * @param	string $alias		Trailer alias
	 * @param	string $unitid		Unit identifier
	 * @param	int $trailertype	Trailer type identifier, possible values: 0: unknown, 1: trailer, 2: generic, 3: carrier, 4: thermoking
	 */
	public function modifyTrailer($trailerid, $depot, $alias, $unitid, $trailertype)
	{
		throw new QFVException('Not Implemented');
	}


	/**
	 * To delete a vehicle, please use DeleteVehicle method
	 * REMARK: Not implemented at this moment.
	 *
	 * @return	bool			true on success
	 * @param 	string $msisdn	Vehicle identifier
	 */
	public function deleteVehicle($msisdn)
	{
		throw new QFVException('Not Implemented');
	}


	/**
	 * To delete a driver, please use DeleteDriver method
	 * REMARK: Not implemented at this moment.
	 *
	 * @return	bool			true on success
	 * @param 	string $cardid	Driver identifier
	 */
	public function deleteDriver($cardid)
	{
		throw new QFVException('Not Implemented');
	}


	/**
	 * To delete a trailer, please use DeleteTrailer method
	 * REMARK: Not implemented at this moment.
	 *
	 * @return	bool				true on success
	 * @param	string $trailerid	Trailer identifier
	 */
	public function deleteTrailer($trailerid)
	{
		throw new QFVException('Not Implemented');
	}
}


/**
 * QFV Exception class
 *
 * @author			Tijs Verkoyen <php-qfv@verkoyen.eu>
 */
class QFVException extends Exception
{
	/**
	 * Http header-codes
	 *
	 * @var	array
	 */
	private $aStatusCodes = array(100 => 'Continue',
									101 => 'Switching Protocols',
									200 => 'OK',
									201 => 'Created',
									202 => 'Accepted',
									203 => 'Non-Authoritative Information',
									204 => 'No Content',
									205 => 'Reset Content',
									206 => 'Partial Content',
									300 => 'Multiple Choices',
									301 => 'Moved Permanently',
									301 => 'Status code is received in response to a request other than GET or HEAD, the user agent MUST NOT automatically redirect the request unless it can be confirmed by the user, since this might change the conditions under which the request was issued.',
									302 => 'Found',
									302 => 'Status code is received in response to a request other than GET or HEAD, the user agent MUST NOT automatically redirect the request unless it can be confirmed by the user, since this might change the conditions under which the request was issued.',
									303 => 'See Other',
									304 => 'Not Modified',
									305 => 'Use Proxy',
									306 => '(Unused)',
									307 => 'Temporary Redirect',
									400 => 'Bad Request',
									401 => 'Unauthorized',
									402 => 'Payment Required',
									403 => 'Forbidden',
									404 => 'Not Found',
									405 => 'Method Not Allowed',
									406 => 'Not Acceptable',
									407 => 'Proxy Authentication Required',
									408 => 'Request Timeout',
									409 => 'Conflict',
									411 => 'Length Required',
									412 => 'Precondition Failed',
									413 => 'Request Entity Too Large',
									414 => 'Request-URI Too Long',
									415 => 'Unsupported Media Type',
									416 => 'Requested Range Not Satisfiable',
									417 => 'Expectation Failed',
									500 => 'Internal Server Error',
									501 => 'Not Implemented',
									502 => 'Bad Gateway',
									503 => 'Service Unavailable',
									504 => 'Gateway Timeout',
									505 => 'HTTP Version Not Supported');


	/**
	 * Default constructor
	 *
	 * @return	void
	 * @param	string[optional] $message
	 * @param	int[optional] $code
	 */
	public function __construct($message = null, $code = null)
	{
		// set message
		if($message === null && isset($this->aStatusCodes[(int) $code])) $message = $this->aStatusCodes[(int) $code];

		// call parent
		parent::__construct((string) $message, $code);
	}
}

?>