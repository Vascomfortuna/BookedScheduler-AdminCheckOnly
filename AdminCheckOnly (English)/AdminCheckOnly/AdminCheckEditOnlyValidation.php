<?php
/**
Copyright 2012-2019 Nick Korbel

This file is part of Booked Scheduler.

Booked Scheduler is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

Booked Scheduler is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Booked Scheduler.  If not, see <http://www.gnu.org/licenses/>.
 */

class AdminCheckEditOnlyValidation implements IReservationValidationService
{
	/**
	 * @var IReservationValidationService
	 */
	private $serviceToDecorate;

	/**
	 * @var UserSession
	 */
	private $userSession;


	public function __construct(IReservationValidationService $serviceToDecorate,
	 														UserSession $userSession)
	{
		$this->serviceToDecorate = $serviceToDecorate;
		$this->userSession = $userSession;
	}

	public function Validate($series, $retryParameters = null)
	{
		$result = $this->serviceToDecorate->Validate($series, $retryParameters);

		if (!$result->CanBeSaved())
		{
			return $result;
		}



		return $this->EvaluateCustomRule($series);
	}

	private function EvaluateCustomRule($series)
	{
		Log::Debug('Starting AdminCheckEditOnly validation.');
		$configFile = Configuration::Instance()->File('AdminCheckOnly'); // Gets config file
		$adminCheckInID = $configFile->GetKey('admincheckonly.attribute.checkin.id'); //Gets AdminCheckInOnly configured ID
		$adminCheckOutID = $configFile->GetKey('admincheckonly.attribute.checkout.id'); //Gets AdminCheckOutOnly configured ID
    $resources = $series->AllResources();
		$adminResources=0; //Resources with AdminCheckInOnly
		$customMessage = $configFile->GetKey('admincheckonly.message.edit.error');

		//Checks if it is Admin
		if($this->userSession->IsAdmin || $this->userSession->IsResourceAdmin || $this->userSession->IsScheduleAdmin){
		   return new ReservationValidationResult();
		}

		//Verifies if CheckIn was done
		//If there is no CheckIn, returns a valid result
		foreach ($series->Instances() as $instance){
			if(!$instance->IsCheckedIn()){
				return new ReservationValidationResult();
			}
		}

		//Verifies if CheckOut was done
		//If there is CheckOut, returns a valid result
		foreach ($series->Instances() as $instance){
			if($instance->IsCheckedOut()){
				return new ReservationValidationResult();
			}
		}

		//Verifica for AdminChecks on resources
		//If there is no AdminCheck, returns valid validation
		foreach ($resources as $key => $resource) {

			$attributeRepository = new AttributeRepository();
			$attributes = $attributeRepository->GetEntityValues(4,$resource->GetId());

			foreach($attributes as $attribute){

	                 if($adminCheckInID == $attribute->AttributeId || $adminCheckOutID == $attribute->AttributeId){
	                   $adminCheckOnly = $attribute->Value; 

										 if($adminCheckOnly){
											 $adminResources++;
											 break;
										 }
	                 }
								 }
	 					 }
	  Log::Debug('Validating AdminCheckEditOnly resources, AdminResources?:%s.', $adminResources);

	  // If there is AdminChecks, returns invalid validation and the configured message
	  if($adminResources){
			return new ReservationValidationResult(false, $customMessage);
		}
		// If there is no AdminChecks
		return new ReservationValidationResult();

 }

}
