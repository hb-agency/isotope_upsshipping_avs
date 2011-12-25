<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * TYPOlight Open Source CMS
 * Copyright (C) 2005-2010 Leo Feyer
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Winans Creative 2011
 * @author     Blair Winans <blair@winanscreative.com>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */
 
 
 
/**
 * UPS Address Verification Tools
 *
 * Overrides default billing/shipping address steps and provides methods for verifying address entry upon checkout and returning error messages to the user
 *
 */
 
class UpsAVS extends Frontend
{

	/**
	 * Form ID
	 * @var string
	 */
	protected $strFormId = 'iso_mod_checkout';
	
	
	/**
	 * Form ID
	 * @var string
	 */
	protected $strAddressFormId = 'avs_select';
	
	
	/**
	 * Initialize the object
	 *
	 * @access public
	 * @param array $arrRow
	 * @param array $arrShipment
	 */
	public function __construct()
	{
		parent::__construct();
		$this->import('Isotope');
		$this->import('IsotopeFrontend');
		
		$GLOBALS['TL_JAVASCRIPT']['UPSAVS'] = 'system/modules/isotope_upsshipping_avs/html/upsshippingavs.js';
		
	}

	

	/**
	 * Callback for Billing address - overrides ModuleIsotopeCheckout
	 *
	 * @access public
	 * @param object Module
	 * @param bool
	 * @return string
	 */
	public function getBillingAddressInterface(&$objModule, $blnReview=false)
	{
		$this->strFormId = 'iso_mod_checkout_address';
		
		$blnRequiresPayment = $this->Isotope->Cart->requiresPayment;

		if ($blnReview)
		{
			return array
			(
				'billing_address' => array
				(
					'headline'	=> ($blnRequiresPayment ? ($this->Isotope->Cart->shippingAddress['id'] == -1 ? $GLOBALS['TL_LANG']['ISO']['billing_shipping_address'] : $GLOBALS['TL_LANG']['ISO']['billing_address']) : (($this->Isotope->Cart->hasShipping && $this->Isotope->Cart->shippingAddress['id'] == -1) ? $GLOBALS['TL_LANG']['ISO']['shipping_address'] : $GLOBALS['TL_LANG']['ISO']['customer_address'])),
					'info'		=> $this->Isotope->generateAddressString($this->Isotope->Cart->billingAddress, $this->Isotope->Config->billing_fields),
					'edit'		=> $this->addToUrl('step=address', true),
				),
			);
		}

		$objTemplate = new IsotopeTemplate('iso_checkout_billing_address_avs');

		$objTemplate->headline = $blnRequiresPayment ? $GLOBALS['TL_LANG']['ISO']['billing_address'] : $GLOBALS['TL_LANG']['ISO']['customer_address'];
		$objTemplate->message = (FE_USER_LOGGED_IN ? $GLOBALS['TL_LANG']['ISO'][($blnRequiresPayment ? 'billing' : 'customer') . '_address_message'] : $GLOBALS['TL_LANG']['ISO'][($blnRequiresPayment ? 'billing' : 'customer') . '_address_guest_message']);
		$objTemplate->fields = $this->generateAddressWidget($objModule, 'billing_address');

		if (!$objModule->doNotSubmit)
		{	
			$this->verifyAddress('billing_address', $objTemplate, $objModule);
			
			$strBillingAddress = $this->Isotope->generateAddressString($this->Isotope->Cart->billingAddress, $this->Isotope->Config->billing_fields);			
			$this->arrOrderData['billing_address']		= $strBillingAddress;
			$this->arrOrderData['billing_address_text']	= strip_tags(str_replace(array('<br />', '<br>'), "\n", $strBillingAddress));
		}

		return $objTemplate->parse();
	}



	/**
	 * Callback for Shipping address - overrides ModuleIsotopeCheckout
	 *
	 * @access public
	 * @param object Module
	 * @param bool
	 * @return string
	 */
	public function getShippingAddressInterface(&$objModule, $blnReview=false)
	{
		$this->strFormId = 'iso_mod_checkout_address';
	
		if (!$this->Isotope->Cart->requiresShipping)
			return '';

		if ($blnReview)
		{
			if ($this->Isotope->Cart->shippingAddress['id'] == -1)
				return false;

			return array
			(
				'shipping_address' => array
				(
					'headline'	=> $GLOBALS['TL_LANG']['ISO']['shipping_address'],
					'info'		=> $this->Isotope->generateAddressString($this->Isotope->Cart->shippingAddress, $this->Isotope->Config->shipping_fields),
					'edit'		=> $this->addToUrl('step=address', true),
				),
			);
		}

		$objTemplate = new IsotopeTemplate('iso_checkout_shipping_address_avs');

		$objTemplate->headline = $GLOBALS['TL_LANG']['ISO']['shipping_address'];
		$objTemplate->message = $GLOBALS['TL_LANG']['ISO']['shipping_address_message'];
		$objTemplate->fields = $this->generateAddressWidget($objModule, 'shipping_address');

		if (!$objModule->doNotSubmit)
		{
			if($this->Isotope->Cart->shippingAddress['id'] >= 0 )
			{
				$this->verifyAddress('shipping_address', $objTemplate, $objModule);
			}
			
			$strShippingAddress = $this->Isotope->Cart->shippingAddress['id'] == -1 ? ($this->Isotope->Cart->requiresPayment ? $GLOBALS['TL_LANG']['MSC']['useBillingAddress'] : $GLOBALS['TL_LANG']['MSC']['useCustomerAddress']) : $this->Isotope->generateAddressString($this->Isotope->Cart->shippingAddress, $this->Isotope->Config->shipping_fields);

			$this->arrOrderData['shipping_address']			= $strShippingAddress;
			$this->arrOrderData['shipping_address_text']	= str_replace('<br />', "\n", $strShippingAddress);
			
			//************************************************************************
			//Unset the multiple shipping step if we do not need it
			if( $_SESSION['CHECKOUT_DATA']['shipping_address']['id'] != 'multiple' )
			{
				unset($GLOBALS['ISO_CHECKOUT_STEPS']['multipleshipping']);
			}
			else
			{
				//Replace existing shipping modules step with multiple one
				$GLOBALS['ISO_CHECKOUT_STEPS']['shipping'] = array(
					array('MultipleShippingFrontend', 'getMultipleShippingModulesInterface')
				);
			}
			//************************************************************************
		}

		return $objTemplate->parse();
	}
	
	
	/**
	 * ModuleIsotopeCheckout Multiple Shipping Address Interface
	 *
	 * Creates an interface that lets the user add addresses to their order and pair those addresses with products
	 *
	 * @access public
	 * @param object
	 * @param bool
	 * @return string
	 */
	public function getMultipleShippingAddressInterface(&$objCheckoutModule, $blnReview=false)
	{	
		$this->strFormId = 'iso_mod_checkout_multipleshipping';
		
		if (!$this->Isotope->Cart->requiresShipping)
			return '';
				
		if ($blnReview)
		{
			if ($this->Isotope->Cart->shippingAddress['id'] != 'multiple')
				return false;

			return array
			(
				'multipleshipping_address' => array
				(
					'headline'	=> $GLOBALS['TL_LANG']['ISO']['multipleshipping_address'],
					'info'		=> $this->Isotope->generateAddressString($this->Isotope->Cart->shippingAddress, $this->Isotope->Config->shipping_fields),
					'edit'		=> $this->addToUrl('step=multipleshipping', true),
				),
			);
		}
				
		$objTemplate = new IsotopeTemplate('iso_checkout_multipleshipping_address');
                
		$objTemplate->headline = $GLOBALS['TL_LANG']['ISO']['multipleshipping_address'];
		$objTemplate->message = $GLOBALS['TL_LANG']['ISO']['multipleshipping_address_message'];
		$objTemplate->addressfields =  $this->generateAddressWidgets('multipleshipping_address', $intOptions, $objCheckoutModule);
		$objTemplate->productfields =  $this->generateProductWidgets('multipleshipping_address', $objCheckoutModule);
		
		if (!$objCheckoutModule->doNotSubmit)
		{
			$objCheckoutModule->arrOrderData['multipleshipping_address']	= $this->Isotope->Cart->multipleshippingAddress;
			
			//Replace existing shipping modules step with multiple one
			$GLOBALS['ISO_CHECKOUT_STEPS']['shipping'] = array(
				array('MultipleShippingFrontend', 'getMultipleShippingModulesInterface')
			);
		}
				
		return $objTemplate->parse();
	}



	/**
	 * Helper function from checkout module since it is protected
	 *
	 * @access public
	 * @param string
	 * @return string
	 */
	protected function generateAddressWidget($objModule, $field)
	{
		$strBuffer = '';
		$arrOptions = array();
		$arrCountries = ($field == 'billing_address' ? $this->Isotope->Config->billing_countries : $this->Isotope->Config->shipping_countries);

		if (FE_USER_LOGGED_IN)
		{
			$objAddress = $this->Database->execute("SELECT * FROM tl_iso_addresses WHERE pid={$this->User->id} AND store_id={$this->Isotope->Config->store_id} ORDER BY isDefaultBilling DESC, isDefaultShipping DESC");

			while( $objAddress->next() )
			{
				if (is_array($arrCountries) && !in_array($objAddress->country, $arrCountries))
					continue;

				$arrOptions[] = array
				(
					'value'		=> $objAddress->id,
					'label'		=> $this->Isotope->generateAddressString($objAddress->row(), ($field == 'billing_address' ? $this->Isotope->Config->billing_fields : $this->Isotope->Config->shipping_fields)),
				);
			}
		}

		switch($field)
		{
			case 'shipping_address':
				$arrAddress = $_SESSION['CHECKOUT_DATA'][$field] ? $_SESSION['CHECKOUT_DATA'][$field] : $this->Isotope->Cart->shippingAddress;
				$intDefaultValue = strlen($arrAddress['id']) ? $arrAddress['id'] : -1;

				array_insert($arrOptions, 0, array(array
				(
					'value'	=> -1,
					'label' => ($this->Isotope->Cart->requiresPayment ? $GLOBALS['TL_LANG']['MSC']['useBillingAddress'] : $GLOBALS['TL_LANG']['MSC']['useCustomerAddress']),
				)));

				$arrOptions[] = array
				(
					'value'	=> 0,
					'label' => $GLOBALS['TL_LANG']['MSC']['differentShippingAddress'],
				);
				break;

			case 'billing_address':
			default:
				$arrAddress = $_SESSION['CHECKOUT_DATA'][$field] ? $_SESSION['CHECKOUT_DATA'][$field] : $this->Isotope->Cart->billingAddress;
				$intDefaultValue = strlen($arrAddress['id']) ? $arrAddress['id'] : 0;

				if (FE_USER_LOGGED_IN)
				{
					$arrOptions[] = array
					(
						'value'	=> 0,
						'label' => &$GLOBALS['TL_LANG']['MSC']['createNewAddressLabel'],
					);
				}
				break;
		}

		// HOOK: add custom addresses, such as from a stored gift registry ******** ADDED BY BLAIR
		if (isset($GLOBALS['ISO_HOOKS']['addCustomAddress']) && is_array($GLOBALS['ISO_HOOKS']['addCustomAddress']))
		{
			foreach ($GLOBALS['ISO_HOOKS']['addCustomAddress'] as $callback)
			{
				$this->import($callback[0]);
				$arrOptions = $this->$callback[0]->$callback[1]($arrOptions, $field, $this);
			}
		}

		if (count($arrOptions))
		{
			$strClass = $GLOBALS['TL_FFL']['radio'];

			$arrData = array('id'=>$field, 'name'=>$field, 'mandatory'=>true);

			$objWidget = new $strClass($arrData);
			$objWidget->options = $arrOptions;
			$objWidget->value = $intDefaultValue;
			$objWidget->onclick = "Isotope.toggleAddressFields(this, '" . $field . "_new');";
			$objWidget->storeValues = true;
			$objWidget->tableless = true;

			// Validate input
			if ($this->Input->post('FORM_SUBMIT') == $this->strFormId)
			{
				$objWidget->validate();

				if ($objWidget->hasErrors())
				{
					$objModule->doNotSubmit = true;
				}
				else
				{
					$_SESSION['CHECKOUT_DATA'][$field]['id'] = $objWidget->value;
				}
			}
			elseif ($objWidget->value != '')
			{
				$this->Input->setPost($objWidget->name, $objWidget->value);

				$objValidator = clone $objWidget;
				$objValidator->validate();

				if ($objValidator->hasErrors())
				{
					$objModule->doNotSubmit = true;
				}
			}

			$strBuffer .= $objWidget->parse();
		}

		if (strlen($_SESSION['CHECKOUT_DATA'][$field]['id']))
		{
			$this->Isotope->Cart->$field = $_SESSION['CHECKOUT_DATA'][$field]['id'];
		}
		elseif (!FE_USER_LOGGED_IN)
		{

		//	$this->doNotSubmit = true;
		}


		$strBuffer .= '<div id="' . $field . '_new" class="address_new"' . (((!FE_USER_LOGGED_IN && $field == 'billing_address') || $objWidget->value == 0) ? '>' : ' style="display:none">');
		$strBuffer .= '<span>' . $this->generateAddressWidgets($objModule, $field, count($arrOptions)) . '</span>';
		$strBuffer .= '</div>';

		return $strBuffer;
	}


	/**
	 * Helper function from MultipleShippingFrontend module since it is protected
	 *
	 * @access public
	 * @param string
	 * @param int
	 * @return string
	 */
	protected function generateAddressWidgets($strAddressType, $intOptions, &$objCheckoutModule)
	{
		$arrBuffer = array();

		$this->loadLanguageFile('tl_iso_addresses');
		$this->loadDataContainer('tl_iso_addresses');

		$arrFields = ($strAddressType == 'billing_address' ? $this->Isotope->Config->billing_fields : $this->Isotope->Config->shipping_fields);
		$arrDefault = $this->Isotope->Cart->$strAddressType;

		if ($arrDefault['id'] == -1)
			$arrDefault = array();

		foreach( $arrFields as $field )
		{
			$arrData = $GLOBALS['TL_DCA']['tl_iso_addresses']['fields'][$field['value']];

			if (!is_array($arrData) || !$arrData['eval']['feEditable'] || !$field['enabled'] || ($arrData['eval']['membersOnly'] && !FE_USER_LOGGED_IN))
				continue;

			$strClass = $GLOBALS['TL_FFL'][$arrData['inputType']];

			// Continue if the class is not defined
			if (!$this->classFileExists($strClass))
				continue;

			// Special field "country"
			if ($field['value'] == 'country')
			{
				$arrCountries = ($strAddressType == 'billing_address' ? $this->Isotope->Config->billing_countries : $this->Isotope->Config->shipping_countries);

				$arrData['options'] = array_values(array_intersect($arrData['options'], $arrCountries));
				$arrData['default'] = $this->Isotope->Config->country;
			}

			// Special field type "conditionalselect"
			elseif (strlen($arrData['eval']['conditionField']))
			{
				$arrData['eval']['conditionField'] = $strAddressType . '_' . $arrData['eval']['conditionField'];
			}

			// Special fields "isDefaultBilling" & "isDefaultShipping"
			elseif (($field['value'] == 'isDefaultBilling' && $strAddressType == 'billing_address' && $intOptions < 2) || ($field['value'] == 'isDefaultShipping' && $strAddressType == 'shippping_address' && $intOptions < 3))
			{
				$arrDefault[$field['value']] = '1';
			}

			$i = count($arrBuffer);
			
			//************************************************************************
			//Custom for multiple shipping
			if($strAddressType == 'multipleshipping_address')
			{
				$objWidget = new $strClass($this->prepareForWidget($arrData, $strAddressType . '_' . $field['value'], (strlen($_SESSION['CHECKOUT_DATA'][$strAddressType][$field['value']]) ? $_SESSION['CHECKOUT_DATA'][$strAddressType][$field['value']] : $arrDefault[$field['value']])));
				$objWidget->mandatory = $field['mandatory'] && $strAddressType =='multipleshipping_address' && $this->Input->post('addAddress') ? true : false;
			}
			else
			{
				$objWidget = new $strClass($this->prepareForWidget($arrData, $strAddressType . '_' . $field['value'], (strlen($_SESSION['CHECKOUT_DATA'][$strAddressType][$field['value']]) ? $_SESSION['CHECKOUT_DATA'][$strAddressType][$field['value']] : $arrDefault[$field['value']])));
				$objWidget->mandatory = $field['mandatory'] ? true : false;
			}
			//************************************************************************

			$objWidget->required = $objWidget->mandatory;
			$objWidget->tableless = $objCheckoutModule->tableless;
			$objWidget->label = $field['label'] ? $this->Isotope->translate($field['label']) : $objWidget->label;
			$objWidget->storeValues = true;
			$objWidget->rowClass = 'row_'.$i . (($i == 0) ? ' row_first' : '') . ((($i % 2) == 0) ? ' even' : ' odd');

			// Validate input
			if ($this->Input->post('FORM_SUBMIT') == $this->strFormId && ($this->Input->post($strAddressType) === '0' || $this->Input->post($strAddressType) == '' || $strAddressType=='multipleshipping_address'))
			{
				$objWidget->validate();

				$varValue = $objWidget->value;

				// Convert date formats into timestamps
				if (strlen($varValue) && in_array($arrData['eval']['rgxp'], array('date', 'time', 'datim')))
				{
					$objDate = new Date($varValue, $GLOBALS['TL_CONFIG'][$arrData['eval']['rgxp'] . 'Format']);
					$varValue = $objDate->tstamp;
				}

				// Do not submit if there are errors
				if ($objWidget->hasErrors())
				{
					$objCheckoutModule->doNotSubmit = true;
				}

				// Store current value
				elseif ($objWidget->submitInput())
				{
					$arrAddress[$field['value']] = $varValue;
				}
			}
			elseif ($this->Input->post($strAddressType) === '0' || $this->Input->post($strAddressType) == '')
			{
				$this->Input->setPost($objWidget->name, $objWidget->value);

				$objValidator = clone $objWidget;
				$objValidator->validate();

				if ($objValidator->hasErrors())
				{
					$objCheckoutModule->doNotSubmit = true;
				}
			}

			$arrBuffer[] = $objWidget->parse();
		}
		
		//************************************************************************
		//Custom for multiple shipping - Add addAddress submit
		if($strAddressType == 'multipleshipping_address')
		{
			$strClass = $GLOBALS['TL_FFL']['submit'];
			$arrData = array('id'=>'addAdress' , 'name'=>'addAddress');
			$objWidget = new $strClass($arrData );
			$objWidget->slabel = $GLOBALS['TL_LANG']['MSC']['addShippingAddress'];
			$objWidget->tableless = $objCheckoutModule->tableless;
			$arrBuffer[] = $objWidget->parse();
			$i++;
		}
		//************************************************************************
		
		// Add row_last class to the last widget
		array_pop($arrBuffer);
		$objWidget->rowClass = 'row_'.$i . (($i == 0) ? ' row_first' : '') . ' row_last' . ((($i % 2) == 0) ? ' even' : ' odd');
		$arrBuffer[] = $objWidget->parse();

		// Validate input
		if ($this->Input->post('FORM_SUBMIT') == $this->strFormId && !$objCheckoutModule->doNotSubmit && is_array($arrAddress) && count($arrAddress))
		{					
			$arrAddress['id'] = 0;
			//************************************************************************
			//Custom for multiple shipping - Array instead of storing value if multipleshipping
			if($strAddressType != 'multipleshipping_address')
			{
				$_SESSION['CHECKOUT_DATA'][$strAddressType] = $arrAddress;
			}
			elseif($this->Input->post('addAddress'))
			{
				$count = count($_SESSION['CHECKOUT_DATA'][$strAddressType]['addresses']) + 1;
				$_SESSION['CHECKOUT_DATA'][$strAddressType]['addresses'][$count] = $arrAddress;
			}
			//************************************************************************
		}
		
		//************************************************************************
		//Custom for multiple shipping - Don't validate if addAddress is present
		if($strAddressType == 'multipleshipping_address' && $this->Input->post('addAddress'))
		{
			$objCheckoutModule->doNotSubmit = true;
		}
		//************************************************************************
		
		//************************************************************************
		//Custom for multiple shipping - Don't need to check for ID
		if (is_array($_SESSION['CHECKOUT_DATA'][$strAddressType]) && ($_SESSION['CHECKOUT_DATA'][$strAddressType]['id'] === 0 || $strAddressType=='multipleshipping_address'))
		{
			$this->Isotope->Cart->$strAddressType = $_SESSION['CHECKOUT_DATA'][$strAddressType];
		}

		if ($objCheckoutModule->tableless)
		{
			return implode('', $arrBuffer);
		}

		return '<table cellspacing="0" cellpadding="0" summary="Form fields">
' . implode('', $arrBuffer) . '
</table>';
	}
	
	/**
	 * Helper function from MultipleShippingFrontend module since it is protected
	 *
	 * Used to match batches of products to multiple shipping destinations
	 *
	 * @access protected
	 * @param string
	 * @param ModuleIsotopeCheckout
	 * @return string
	 */
	protected function generateProductWidgets( $strField, &$objCheckoutModule )
	{	
		$arrBuffer = array();
						
		//Get existing Cart products
		$arrProducts = $this->Isotope->Cart->getProducts();
		
		//Add empty option
		$arrOptions[] = array
		(
			'value'	=> '',
			'label' =>  $GLOBALS['TL_LANG']['MSC']['selectAddress'],
		);
		
		//Get existing addresses, starting with billing address as an option
		$arrOptions[] = array
		(
			'value'	=> -1,
			'label' =>  ($this->Isotope->Cart->requiresPayment ? $GLOBALS['TL_LANG']['MSC']['useBillingAddress'] : $GLOBALS['TL_LANG']['MSC']['useCustomerAddress']),
		);

		if(is_array($_SESSION['CHECKOUT_DATA'][$strField]['addresses']))
		{
			foreach($_SESSION['CHECKOUT_DATA'][$strField]['addresses'] as $key=>$address)
			{
				$arrOptions[] = array
				(
					'value'	=> $key,
					'label' =>  $address['lastname'] . ', ' . $address['firstname'] . ': ' . $address['city'] . ', ' . $address['subdivision'],
				);
			}
		}
		
		foreach($arrProducts as $i => $objProduct)
		{
			for($j = 0; $j < $objProduct->quantity_requested; $j++)
			{
				$strClass = $GLOBALS['TL_FFL']['select'];

				$arrData = array('id'=>$strField . '['.$objProduct->id.']['.$j.']' , 'name'=>$strField . '['.$objProduct->cart_id.']['.$j.']');
			
				$objWidget = new $strClass($arrData);
				$objWidget->mandatory = ($this->Input->post('nextStep') && !strlen($_SESSION['CHECKOUT_DATA'][$strField]['products'][$objProduct->cart_id][$j])) ? true : false;
				$objWidget->required = $objWidget->mandatory;
				$objWidget->options = $arrOptions;
				$objWidget->value = $_SESSION['CHECKOUT_DATA'][$strField]['products'][$objProduct->cart_id][$j];
				$objWidget->storeValues = true;
				$objWidget->tableless = $objCheckoutModule->tableless;
				$objWidget->label = $objProduct->images->generateMainImage('gallery') . '<span>'. $objProduct->name . '</span>';
				
				// Validate input
				if ($this->Input->post('FORM_SUBMIT') == $this->strFormId)
				{
					$objWidget->validate();
	
					if ($objWidget->hasErrors())
					{
						$objCheckoutModule->doNotSubmit = true;
					}
					else
					{
						$_SESSION['CHECKOUT_DATA'][$strField]['products'][$objProduct->cart_id][$j] = $objWidget->value;
					}
				}
				elseif ($objWidget->value != '')
				{
					$this->Input->setPost($objWidget->name, $objWidget->value);
	
					$objValidator = clone $objWidget;
					$objValidator->validate();
	
					if ($objValidator->hasErrors())
					{
						$objCheckoutModule->doNotSubmit = true;
					}
				}
				
				$arrBuffer[] = $objWidget->parse();
				
			}
		}
				
		if ($objCheckoutModule->tableless)
		{
			return implode('', $arrBuffer);
		}

		return '<table cellspacing="0" cellpadding="0" summary="Form fields">
' . implode('', $arrBuffer) . '
</table>';
	
	}

	
	
	/**
	 * Verify an address submission using UPS AVS API
	 *
	 * @access protected
	 * @param string
	 * @param object IsotopeTemplate
	 * @param object ModuleIsotope
	 * @return void
	 */
	protected function verifyAddress( $strAddressType, &$objTemplate, &$objModule )
	{
		//Build and send request
		$arrDestination = $this->buildAddress('billing_address');
		$objUPSAPI = new UpsAPIAVSStreet($arrDestination);
		$xmlShip = $objUPSAPI->buildRequest();
		$arrResponse = $objUPSAPI->sendRequest($xmlShip);
				
		//Get the responses into a manageable format
		list(	$intResponseCode, 
				$intAddressClassificationCode, 
				$strAddressClassification, 
				$blnValidAddress, 
				$blnAmbiguousAddress, 
				$blnNoCandidates, 
				$arrAddresses
				) = $this->cleanUpResponse( $arrResponse );
										
		//Address validation failed					
		if( $intResponseCode == 1 && ($blnAmbiguousAddress || $blnNoCandidates )) 
		{
			$objModule->doNotSubmit = true;
			
			//Address was ambiguous. Ask user to choose from provided options or re-enter
			if($blnAmbiguousAddress && is_array($arrAddresses) ) 
			{									
				$objTemplate->selectAddress = true;
				$objTemplate->popupForm = $this->getAddressPopup('billing_address', $arrAddresses, 'ambiguous', 4);
				
			}
			//Address was a total failure
			else
			{
				$_SESSION['ISO_ERROR']['invalidaddress'] = $GLOBALS['TL_LANG']['UPS']['AVS']['invalidHeadline'];
				$this->IsotopeFrontend->injectMessages();
			}
			
		}
		//Address was valid, but we still need to check street/city/ and replace values if different
		//UPS will respond and give you a corrected address but still list it as valid. So stupid.
		elseif($intResponseCode == 1 && $blnValidAddress && is_array($arrAddresses)) 
		{
			$arrCurrent = $this->Isotope->Cart->$strAddressType;
			$arrParsed = $this->parseAddresses($strAddressType, $arrAddresses);
			$arrSet = $arrParsed[0]['raw'];
			
			$arrFields = array('company', 'street_1', 'street_2', 'street_3', 'city', 'zip', 'address_classification');
										
			foreach($arrCurrent as $field=>$value)
			{
				if( strtoupper($arrSet[$field]) !== strtoupper($value) && in_array($field, $arrFields) && strlen($arrSet[$field]))
				{
					$arrCurrent[$field] = $arrSet[$field];
					$_SESSION['CHECKOUT_DATA'][$strAddressType][$field] = $arrSet[$field];
				}
			}
			
			$this->Isotope->Cart->$strAddressType = $arrCurrent;
			
		}
		//Request failed. Ask user to resubmit.
		else 
		{
			$objModule->doNotSubmit = true;
			$_SESSION['ISO_ERROR']['invalidaddress'] = $GLOBALS['TL_LANG']['UPS']['AVS']['invalidHeadline'];
			$this->IsotopeFrontend->injectMessages();
		}	
	
	}
	
	
	
	/**
	 * Function to build an address array suitable for UPS API
	 *
	 * @access protected
	 * @param array
	 * @return array
	 */
	protected function buildAddress($strAddressType)
	{
		$arrDestination = $this->Isotope->Cart->$strAddressType;
	
		$arrAddress = array();
		
		$arrSubDivisionShipping = explode('-',$arrDestination['subdivision']);
	
		$arrAddress = array
		(
			'name'			=> $arrDestination['firstname'] . ' ' . $arrDestination['lastname'],
			'company'		=> $arrDestination['company'],
			'street'		=> strtoupper($arrDestination['street_1']),
			'street2'		=> strtoupper($arrDestination['street_2']),
			'street3'		=> strtoupper($arrDestination['street_3']),
			'city'			=> strtoupper($arrDestination['city']),
			'state'			=> $arrSubDivisionShipping[1],
			'zip'			=> $arrDestination['postal'],
			'country'		=> strtoupper($arrDestination['country'])
		);

		return $arrAddress;
	}
	
	
	/**
	 * Function to parse an address array from the UPS API suitable for Isotope
	 * Returns a label for use in input fields and the raw array
	 *
	 * @access protected
	 * @param string
	 * @param array
	 * @param int
	 * @return array
	 */
	protected function parseAddresses($strAddressType, $arrAddresses)
	{
		$arrParsed = array();
				
		foreach($arrAddresses as $arrAddress)
		{			
			$arrCurrent = $_SESSION['CHECKOUT_DATA'][$strAddressType];
			
			if(is_array($arrAddress["AddressLine"]))
			{
				$arrAddress["AddressLine1"] = $arrAddress["AddressLine"][0];
				$arrAddress["AddressLine2"] = $arrAddress["AddressLine"][1];
				$arrAddress["AddressLine3"] = $arrAddress["AddressLine"][2] ? $arrAddress["AddressLine"][2] : '';
			}
			else
			{
				$arrAddress["AddressLine1"] = $arrAddress["AddressLine"];
				$arrAddress["AddressLine2"] = '';
				$arrAddress["AddressLine3"] = '';
			}
			
			$arrAddress["Company"] = ($arrAddress["ConsigneeName"] ? $arrAddress["ConsigneeName"] : $arrCurrent['company']);
			
			$arrParse = array
			(
				'firstname'		=> $arrCurrent['firstname'],
				'lastname'		=> $arrCurrent['lastname'],
				'company'		=> $arrAddress["Company"],
				'street_1'		=> $arrAddress["AddressLine1"],
				'street_2'		=> $arrAddress["AddressLine2"],
				'street_3'		=> $arrAddress["AddressLine3"],
				'city'			=> $arrAddress["PoliticalDivision2"],
				'state'			=> strtoupper($arrAddress["PoliticalDivision1"]),
				'subdivision'	=> strtoupper($arrAddress["CountryCode"]) . '-' . strtoupper($arrAddress["PoliticalDivision1"]),
				'postal'		=> $arrAddress["PostcodePrimaryLow"],
				'country'		=> strtolower($arrAddress["CountryCode"]),
				'address_classification'	=> strtolower($arrAddress["AddressClassification"]["Description"])
			);
			
			$arrParsed[] = array
			(
				'label'	=>	$this->Isotope->generateAddressString($arrParse, ($strAddressType == 'billing_address' ? $this->Isotope->Config->billing_fields : $this->Isotope->Config->shipping_fields)),
				'raw'	=> $arrParse
			);
			
		}

		return $arrParsed;
	}
	
	
	/**
	 * Returns a modal popup for selecting optional addresses as returned by UPS and/or handles non-Javascript form submit
	 *
	 * @access protected
	 * @param string
	 * @param array
	 * @param string
	 * @return string
	 */
	protected function getAddressPopup($strAddressType, $arrAddresses, $strErrorType, $intLimit=50)
	{
		$blnAddressRange = false;
		$arrRanges = array();
		$arrJSON = array();
		
		$objTemplate = new IsotopeTemplate('iso_checkout_avs_popup');
		
		$arrParsedAddresses = $this->parseAddresses($strAddressType, $arrAddresses);
		$arrCurrent = $_SESSION['CHECKOUT_DATA'][$strAddressType];
		
		//Check for address range flag
		foreach($arrParsedAddresses as $address)
		{
			//Range flag should only be used if city and state are the same
			if($arrCurrent['city']==$address['raw']['city'] && $arrCurrent['subdivision']==$address['raw']['subdivision'])
			{
				$arrStreet = explode(' ', $address['raw']['street_1']);
				$arrRange = explode('-', $arrStreet[0]);
				if(is_array($arrRange) && count($arrRange) > 1 && ctype_digit($arrRange[0]) && ctype_digit($arrRange[1]) )
				{
					$arrRanges[] = $arrRange[0];
					$arrRanges[] = $arrRange[1];
					$blnAddressRange = true;
				}
			}			
			//Also get all the raw into an array so we can JSON encode
			$arrJSON[] = $address['raw'];
		}
		
		//Address range flag was hit. Basically the address is fine expect for the street number
		//No need to have someone select from addresses since UPS returns addresses with address ranges which will not validate again
		if($blnAddressRange && $strErrorType=='ambiguous')
		{
			sort($arrRanges, SORT_NUMERIC);
			$_SESSION['ISO_ERROR']['addressrange'] = sprintf($GLOBALS['TL_LANG']['UPS']['AVS']['rangeHeadline'], array_shift($arrRanges), array_pop($arrRanges) );
			$this->IsotopeFrontend->injectMessages();
			return '';
		}
		
		$objTemplate->formSubmit = $this->strAddressFormId . '_' . $strAddressType;
		$objTemplate->radioId = $this->strAddressFormId . '_' . $strAddressType . '_radio';
		
		//Handle non-JS form submission
		if( $this->Input->post('UPSAVS_SUBMIT')==$objTemplate->formSubmit && strlen($this->Input->post($objTemplate->radioId)) )
		{
			$arrSet = $arrJSON[$this->Input->post($objTemplate->radioId)];
			$arrFields = array('company', 'street_1', 'street_2', 'street_3', 'city', 'zip');
										
			foreach($arrNewAddress as $field=>$value)
			{
				if( strtoupper($arrSet[$field]) !== strtoupper($value) && in_array($field, $arrFields) && strlen($arrSet[$field]))
				{
					$arrCurrent[$field] = $arrSet[$field];
					$_SESSION['CHECKOUT_DATA'][$strAddressType][$field] = $arrSet[$field];
				}
			}
			
			//Set new address to the Cart and reload
			$this->Isotope->Cart->$strAddressType = $arrCurrent;
			$this->reload();
		}
		
		$objTemplate->popupId = $this->strAddressFormId . '_'. $strAddressType . '_popup';
		$objTemplate->headline = $GLOBALS['TL_LANG']['UPS']['AVS'][$strErrorType. 'Headline'];
		$objTemplate->addresses = count($arrParsedAddresses) <= $intLimit ? $arrParsedAddresses :  array_slice($arrParsedAddresses, 0, $intLimit);
		
		list(,$startScript, $endScript) = IsotopeFrontend::getElementAndScriptTags();
		
		$arrJSON = count($arrJSON) <= $intLimit ? $arrJSON : array_slice($arrJSON, 0, $intLimit);
		
		$GLOBALS['TL_MOOTOOLS'][] = "
$startScript
window.addEvent('domready', function() {
	new UPSAVS('".$objTemplate->popupId."', '".$this->strFormId."', '".$strAddressType."', '".json_encode($arrJSON)."');
});
$endScript";

		return $objTemplate->parse();
	
	}
	
	
	/**
	 * Returns an array of formatted responses from the crazy UPS Response Array
	 *
	 * @access protected
	 * @param array
	 * @return array
	 */
	protected function cleanUpResponse( $arrResponse )
	{
		$intResponseCode = (int)$arrResponse["AddressValidationResponse"]["Response"]["ResponseStatusCode"];
		$intAddressClassificationCode = (int)$arrResponse["AddressValidationResponse"]["AddressClassification"]["Code"];
		$strAddressType = $arrResponse["AddressValidationResponse"]["AddressClassification"]["Description"];
		$blnValidAddress = is_array($arrResponse["AddressValidationResponse"]["ValidAddressIndicator"]);
		$blnAmbiguousAddress = is_array($arrResponse["AddressValidationResponse"]["AmbiguousAddressIndicator"]);
		$blnNoCandidates = is_array($arrResponse["AddressValidationResponse"]["NoCandidatesIndicator"]);
		$arrAddresses = $arrResponse["AddressValidationResponse"]["AddressKeyFormat"]["PostcodePrimaryLow"] ? array($arrResponse["AddressValidationResponse"]["AddressKeyFormat"]) : $arrResponse["AddressValidationResponse"]["AddressKeyFormat"];
		
		return array($intResponseCode, $intAddressClassificationCode, $strAddressType, $blnValidAddress, $blnAmbiguousAddress, $blnNoCandidates, $arrAddresses);
	}

}