<?if( !defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true ) die();

if( CModule::IncludeModule("iblock") ){
	$GLOBALS['strError'] = '';
	
	if( !is_set( $arParams["CACHE_TIME"] ) ){
		$arParams["CACHE_TIME"] = "3600";
	}
	
	$bCache = !( $_SERVER["REQUEST_METHOD"] == "POST" && !empty( $_REQUEST["form_submit"] ) || $_REQUEST['formresult'] == 'ADDOK' ) && !( $arParams["CACHE_TYPE"] == "N" || ( $arParams["CACHE_TYPE"] == "A" && COption::GetOptionString("main", "component_cache_on", "Y") == "N" ) || ( $arParams["CACHE_TYPE"] == "Y" && intval($arParams["CACHE_TIME"]) <= 0 ) );
	
	if( $bCache ){
		$arCacheParams = array();
		foreach( $arParams as $key => $value )
			if( substr($key, 0, 1) != "~" )
				$arCacheParams[$key] = $value;
		$obFormCache = new CPHPCache;
		$CACHE_ID = SITE_ID."|".$componentName."|".md5(serialize($arCacheParams));
		if( ( $tzOffset = CTimeZone::GetOffset() ) <> 0 )
			$CACHE_ID .= "|".$tzOffset;
		$CACHE_PATH = "/".SITE_ID.CComponentEngine::MakeComponentPath( $componentName );
	}
	
	if( $bCache && $obFormCache->InitCache( $arParams["CACHE_TIME"], $CACHE_ID, $CACHE_PATH ) ){
		$arCacheVars = $obFormCache->GetVars();
		$bVarsFromCache = true;
		
		$arResult = $arCacheVars["arResult"];
		$arResult["FORM_NOTE"] = "";
		$arResult["isFormNote"] = "N";
	}else{
		$bVarsFromCache = false;
		
		if( $arParams["IBLOCK_ID"] > 0 ){
			$arResult["F_RIGHT"] = CIBlock::GetPermission( $arParams["IBLOCK_ID"] );
			
			if( $arResult["F_RIGHT"] == "D" || $arResult["F_RIGHT"] == "R" ){
				$arResult["ERROR"] = "FORM_ACCESS_DENIED";
			}
		}else{
			$arResult["ERROR"] = "FORM_NOT_FOUND";
		}
		
		$arResult["EVENT_TYPE"] = "ASPRO_SEND_FORM";
		
		$arIBlock = CIBlock::GetList( false, array( "ID" => $arParams["IBLOCK_ID"] ) )->Fetch();
		$arResult["IBLOCK_CODE"] = $arIBlock["CODE"];
		$arResult["IBLOCK_TITLE"] = $arIBlock["NAME"];
		$arResult["IBLOCK_DESCRIPTION"] = $arIBlock["DESCRIPTION"];
		$arResult["IBLOCK_DESCRIPTION_TYPE"] = $arIBlock["DESCRIPTION_TYPE"];
		
		$rsProp = CIBlock::GetProperties( $arParams["IBLOCK_ID"], array( "SORT"=> "ASC" ) );
		while( $arProp = $rsProp->Fetch() ){
			$arResult["arQuestions"][] = $arProp;
		}
		
		foreach( $arResult["arQuestions"] as $key => $arQuestion ){
			$tmp = array(
				"NAME" => $arQuestion["NAME"],
				"CODE" => $arQuestion["CODE"],
				"IS_REQUIRED" => $arQuestion["IS_REQUIRED"],
				"HINT" => $arQuestion["HINT"],
				"DEFAULT" => $arQuestion["DEFAULT_VALUE"],
				"ICON" => $arQuestion["XML_ID"]
			);
			if( $arQuestion["PROPERTY_TYPE"] == "S" && empty( $arQuestion["USER_TYPE"] ) ){
				$tmp["FIELD_TYPE"] = "text";
			}elseif( $arQuestion["PROPERTY_TYPE"] == "S" && !empty( $arQuestion["USER_TYPE"] ) && $arQuestion["USER_TYPE"] == "DateTime" ){
				$tmp["FIELD_TYPE"] = "date";
			}elseif( $arQuestion["PROPERTY_TYPE"] == "S" && !empty( $arQuestion["USER_TYPE"] ) && $arQuestion["USER_TYPE"] == "HTML" ){
				$tmp["FIELD_TYPE"] = "html";
			}elseif( $arQuestion["PROPERTY_TYPE"] == "N" ){
				$tmp["FIELD_TYPE"] = "integer";
			}elseif( $arQuestion["PROPERTY_TYPE"] == "L" && $arQuestion["LIST_TYPE"] == "L" ){
				$tmp["FIELD_TYPE"] = "list";
			}elseif( $arQuestion["PROPERTY_TYPE"] == "L" && $arQuestion["LIST_TYPE"] == "C" ){
				$tmp["FIELD_TYPE"] = "checkbox";
			}elseif( $arQuestion["PROPERTY_TYPE"] == "F" ){
				$tmp["FIELD_TYPE"] = "file";
			}else{
				continue;
			}
			
			$tmp["MULTIPLE"] = $arQuestion["MULTIPLE"];
			
			if( $tmp["FIELD_TYPE"] != 'checkbox' ){
				$tmp["CAPTION"] = '<label for="'.$arQuestion["CODE"].'">'.$arQuestion["NAME"].': '.($arQuestion["IS_REQUIRED"] == "Y" ? '<span class="required-star">*</span>' : '').'</label>';
			}
			
			$arResult["QUESTIONS"][$arQuestion["CODE"]] = $tmp;
		}
		
		$arResult["SITE"] = CSite::GetByID(SITE_ID)->Fetch();
		
		if( $bCache ){
			$obFormCache->StartDataCache();
			$GLOBALS['CACHE_MANAGER']->StartTagCache($CACHE_PATH);
			$GLOBALS['CACHE_MANAGER']->RegisterTag("forms");
			$GLOBALS['CACHE_MANAGER']->RegisterTag("form_".$arParams["IBLOCK_ID"]);
			$GLOBALS['CACHE_MANAGER']->EndTagCache();
			$obFormCache->EndDataCache(
				array(
					"arResult" => $arResult,
				)
			);
		}
	}
	
	$event_name = $arResult["EVENT_TYPE"]."_".$arParams["IBLOCK_ID"];
	
	$arEvent = CEventType::GetByID( $event_name, $arResult["SITE"]["LANGUAGE_ID"] )->Fetch();
	if( !is_array( $arEvent ) ){
		$et = new CEventType;
		
		$desc = '';
		foreach( $arResult["QUESTIONS"] as $FIELD_CODE => $arQuestion ){
			$desc .= $arQuestion["NAME"].": "."#".$FIELD_CODE."#\n";
		}
		$desc .= GetMessage("FORM_ET_DESCRIPTION");
		$et->Add( array(
			"LID" => $arResult["SITE"]["LANGUAGE_ID"],
			"EVENT_NAME" => $event_name,
			"NAME" => GetMessage("FORM_ET_NAME"),
			"DESCRIPTION" => $desc
		));
	}
	
	$event_name_admin = $arResult["EVENT_TYPE"]."_ADMIN_".$arParams["IBLOCK_ID"];
	
	$arEvent = CEventType::GetByID( $event_name_admin, $arResult["SITE"]["LANGUAGE_ID"] )->Fetch();
	if( !is_array( $arEvent ) ){
		$et = new CEventType;
		
		$desc = '';
		foreach( $arResult["QUESTIONS"] as $FIELD_CODE => $arQuestion ){
			$desc .= $arQuestion["NAME"].": "."#".$FIELD_CODE."#\n";
		}
		$desc .= GetMessage("FORM_ET_DESCRIPTION");
		$et->Add( array(
			"LID" => $arResult["SITE"]["LANGUAGE_ID"],
			"EVENT_NAME" => $event_name_admin,
			"NAME" => GetMessage("FORM_ET_NAME"),
			"DESCRIPTION" => $desc
		));
	}
	
	$arMess = CEventMessage::GetList( $arResult["SITE"]["ID"], $order="desc", array( "TYPE_ID" => $event_name ) )->Fetch();
	if( !is_array( $arMess ) ){
		$em = new CEventMessage;
		$arMess = array();
		$arMess["ID"] = $em->Add( array(
			"ACTIVE" => "Y",
			"EVENT_NAME" => $event_name,
			"LID" => array( $arResult["SITE"]["LID"] ),
			"EMAIL_FROM" => "#DEFAULT_EMAIL_FROM#",
			"EMAIL_TO" => "#EMAIL#",
			"BCC" => "#BCC#",
			"SUBJECT" => GetMessage("FORM_EM_NAME"),
			"BODY_TYPE" => "text",
			"MESSAGE" => GetMessage("FORM_EM_START")."#MESSAGE_BODY#".GetMessage("FORM_EM_END")
		) );
		$arMess["EVENT_NAME"] = $event_name;
	}
	
	$arMess = CEventMessage::GetList( $arResult["SITE"]["ID"], $order="desc", array( "TYPE_ID" => $event_name_admin ) )->Fetch();
	if( !is_array( $arMess ) ){
		$em = new CEventMessage;
		$arMess = array();
		$arMess["ID"] = $em->Add( array(
			"ACTIVE" => "Y",
			"EVENT_NAME" => $event_name_admin,
			"LID" => array( $arResult["SITE"]["LID"] ),
			"EMAIL_FROM" => "#DEFAULT_EMAIL_FROM#",
			"EMAIL_TO" => "#EMAIL#",
			"BCC" => "#BCC#",
			"SUBJECT" => GetMessage("FORM_EM_NAME"),
			"BODY_TYPE" => "text",
			"MESSAGE" => GetMessage("FORM_EM_START")."#MESSAGE_BODY#".GetMessage("FORM_EM_END")
		) );
		$arMess["EVENT_NAME"] = $event_name_admin;
	}
	
	foreach( $arResult["QUESTIONS"] as $FIELD_CODE => $arQuestion ){
		$val = !empty( $_REQUEST[$arQuestion["CODE"]] ) ? $_REQUEST[$arQuestion["CODE"]] : $arQuestion["DEFAULT"];
		$val = htmlspecialchars( $val );
		if($arQuestion["CODE"]=="SERVICE") $val=$arParams["SERVICE"];
		$required = $arQuestion["IS_REQUIRED"] == "Y" ? 'required' : '';
		$phone = strpos( $arQuestion["CODE"], "PHONE" ) !== false ? 'phone' : '';
		$placeholder = $arParams["IS_PLACEHOLDER"] == "Y" ? 'placeholder="'.$arQuestion["NAME"].'"' : '';
		$icon = !empty( $arQuestion["ICON"] ) ? '<i class="icon '.$arQuestion["ICON"].'"></i>' : '';
		
		$html = '';
		
		switch( $arQuestion["FIELD_TYPE"] ){
			case "text":
				$html = '<input type="'.( $arQuestion["CODE"] == "EMAIL" ? "email" : "text" ).'" id="'.$arQuestion["CODE"].'" name="'.$arQuestion["CODE"].'" class="form-control '.$required.' '.$phone.'" '.$placeholder.' value="'.$val.'" />'.$icon;
				break;
			case "integer":
				$html = '<input type="number" id="'.$arQuestion["CODE"].'" name="'.$arQuestion["CODE"].'" class="form-control '.$required.'" '.$placeholder.' value="'.$val.'" />'.$icon;
				break;
			case "date":
				$html = '<input type="date" id="'.$arQuestion["CODE"].'" name="'.$arQuestion["CODE"].'" class="form-control '.$required.'" '.$placeholder.' value="'.$val.'" />'.$icon;
				break;
			case "html":
				$html = '<textarea id="'.$arQuestion["CODE"].'" name="'.$arQuestion["CODE"].'" class="form-control '.$required.'" '.$placeholder.'>'.$val.'</textarea>'.$icon;
				break;
			case "list":
				$rsValue = CIBlockProperty::GetPropertyEnum( $arQuestion["CODE"], array( "SORT" => "ASC", "ID" => "ASC" ) );
				$html = '<select id="'.$arQuestion["CODE"].'" name="'.$arQuestion["CODE"].'" class="form-control '.$required.'" '.$placeholder.'>';
				while( $arValue = $rsValue->Fetch() ){
					$selected = '';
					if( !empty( $val ) && $arValue["XML_ID"] == $val ){
						$selected = 'selected="selected"';
					}
					if( empty( $val ) && $arValue["DEF"] == "Y" ){
						$selected = 'selected="selected"';
					}
					$html .= '<option value="'.$arValue["XML_ID"].'" '.$selected.' >'.$arValue["VALUE"].'</option>';
				}
				$html .= '</select>'.$icon;
				break;
			case "checkbox":
				$html = '';
				$rsValue = CIBlockProperty::GetPropertyEnum( $arQuestion["CODE"], array( "SORT" => "ASC", "ID" => "ASC" ) );
				$count = 0;
				while( $arValue = $rsValue->Fetch() ){
					$count++;
				}
				$rsValue = CIBlockProperty::GetPropertyEnum( $arQuestion["CODE"], array( "SORT" => "ASC", "ID" => "ASC" ) );
				while( $arValue = $rsValue->Fetch() ){
					$artmpValue = $arValue;
					$checked = '';
					if( !empty( $val ) && $arValue["ID"] == $val ){
						$checked = 'checked="checked"';
					}
					if( empty( $val ) && $arValue["DEF"] == "Y" ){
						$checked = 'checked="checked"';
					}
					$html .= '<input class="'.$required.'" id="'.$arValue["ID"].'" name="'.$arQuestion["CODE"].'" type="checkbox" value="'.$arValue["ID"].'" '.$checked.' />';
					if( $count == 1 ){
						$html .= '<label for="'.$arValue["ID"].'">'.$arQuestion["NAME"].'</label>';
					}else{
						$html .= '<label for="'.$arValue["ID"].'">'.$arValue["VALUE"].'</label>';
					}
				}
				break;
			case "file":
				if( $arQuestion["MULTIPLE"] == "Y" ){
					$html = '';
					for( $i = 0; $i < 5; $i++ ){
						$html .= '<input type="file" id="'.$arQuestion["CODE"].'" name="'.$arQuestion["CODE"].'_n'.$i.'" '.$required.' '.$placeholder.' value="'.$val.'" />'.$icon;
					}
				}else{
					$html = '<input type="file" id="'.$arQuestion["CODE"].'" name="'.$arQuestion["CODE"].'" '.$required.' '.$placeholder.' value="'.$val.'" />'.$icon;
				}
				break;
		}
		
		$arResult["QUESTIONS"][$FIELD_CODE]["VALUE"] = $val;
		$arResult["QUESTIONS"][$FIELD_CODE]["HTML_CODE"] = $html;
	}
	
	if( strlen( $arResult["ERROR"] ) <= 0 ){
		if( strlen( $_REQUEST["form_submit"] ) > 0 ){
			foreach( $arResult["QUESTIONS"] as $FIELD_CODE => $arQuestion ){
				if( empty( $_REQUEST[$FIELD_CODE] ) && $arQuestion["IS_REQUIRED"] == "Y" ){
					$arResult["FORM_ERRORS"][] = GetMessage("FORM_REQUIRED_INPUT").$arQuestion["NAME"];
				}
			}
			
			if( $arParams["USE_CAPTCHA"] == "Y" && ( empty( $_REQUEST["captcha_word"] ) || !$APPLICATION->CaptchaCheckCode( $_REQUEST["captcha_word"], $_REQUEST["captcha_sid"] ) ) ){
				$arResult["FORM_ERRORS"][] = GetMessage("FORM_CAPTCHA");
				$captcha_error = true;
			}
			if( count( $arResult["FORM_ERRORS"] ) <= 0 ){
				//if( check_bitrix_sessid() ){
					$_REQUEST["MESSAGE"] = Array( "VALUE" => Array ("TEXT" => $_REQUEST["MESSAGE"], "TYPE" => "html") );
					$arPropFields = $_REQUEST;
					
					foreach( $_FILES as $key => $arFile ){
						$code = explode('_', $key);
						$arPropFields[$code[0]][$code[1]] = $arFile;
					}
					
					$el = new CIBlockElement;
					
					$arFields = array(
						"IBLOCK_ID" => $arParams["IBLOCK_ID"],
						"ACTIVE" => "Y",
						"NAME" => GetMessage("DEFAULT_NAME").ConvertTimeStamp(),
						"PROPERTY_VALUES" => $arPropFields
					);
					
					foreach( GetModuleEvents("aspro.form", "OnBeforeFormSend", true) as $arEvent )
						ExecuteModuleEventEx($arEvent, array(&$arFields));
					
					if( $RESULT_ID = $el->Add( $arFields ) ){
						$arResult["FORM_RESULT"] = "ADDOK";
						foreach( GetModuleEvents("aspro.form", "OnAfterFormSend", true) as $arEvent )
							ExecuteModuleEventEx($arEvent, array(&$arFields));
						
						$message = "";
						foreach( $arResult["QUESTIONS"] as $FIELD_CODE => $arQuestion ){
							if( !empty( $arFields["PROPERTY_VALUES"][$FIELD_CODE] ) && !is_array( $arFields["PROPERTY_VALUES"][$FIELD_CODE] ) ){
								$message .= $arQuestion["NAME"].": ".$arFields["PROPERTY_VALUES"][$FIELD_CODE]."\n";
							}
						}
						
						$arEventFields = array(
							"SITE_NAME" => $arResult["SITE"]["NAME"],
							"FORM_NAME" => $arResult["NAME"],
							"EMAIL" => $arResult["QUESTIONS"]["EMAIL"]["VALUE"],
							"BCC" => $arResult["SITE"]["EMAIL"],
							"MESSAGE_BODY" => $message
						);
						
						CEvent::SendImmediate( $arMess["EVENT_NAME"]."_".$arParams["IBLOCK_ID"], SITE_ID, $arEventFields, "Y", $arMess["ID"] );
						
						$arEventFields = array(
							"SITE_NAME" => $arResult["SITE"]["NAME"],
							"FORM_NAME" => $arResult["NAME"],
							"EMAIL" => $arResult["SITE"]["EMAIL"],
							"MESSAGE_BODY" => $message
						);
						
						CEvent::SendImmediate( $arMess["EVENT_NAME"]."_ADMIN_".$arParams["IBLOCK_ID"], SITE_ID, $arEventFields, "Y", $arMess["ID"] );
						
						if( $arParams["SEF_MODE"] == "Y" ){
							LocalRedirect(
								$APPLICATION->GetCurPageParam(
									"formresult=".urlencode($arResult["FORM_RESULT"]),
									array('formresult', 'strFormNote', 'SEF_APPLICATION_CUR_PAGE_URL')
								)
							);
							
							die();
						}else{
							LocalRedirect(
								$APPLICATION->GetCurPageParam(
									"IBLOCK_ID=".$arParams["IBLOCK_ID"]
									."&RESULT_ID=".$RESULT_ID
									."&formresult=".urlencode($arResult["FORM_RESULT"]),
									array('formresult', 'strFormNote', 'IBLOCK_ID', 'RESULT_ID')
								)
							);
							
							die();
						}
					}else{
						echo "Error: ".$el->LAST_ERROR;
						$arResult["FORM_ERRORS"] = array($GLOBALS["strError"]);
					}
				//}
			}
		}
		
		if( !empty( $_REQUEST["formresult"] ) && strtoupper($_REQUEST["formresult"]) == "ADDOK" ){
			$arResult['FORM_NOTE'] = !empty( $arParams["SUCCESS_MESSAGE"] ) ? $arParams["~SUCCESS_MESSAGE"] : GetMessage('FORM_NOTE_ADDOK');
		}
		
		$arResult["isFormErrors"] = count( $arResult["FORM_ERRORS"] ) > 0 ? "Y" : "N";
		
		if( $arParams["USE_CAPTCHA"] == "Y" )
			$arResult["CAPTCHACode"] = $APPLICATION->CaptchaGetCode();
		
		$arResult = array_merge(
			$arResult,
			array(
				"isFormNote" => strlen( $arResult["FORM_NOTE"] ) ? "Y" : "N",
				
				"FORM_HEADER" => sprintf(
					"<form name=\"%s\" action=\"%s\" method=\"%s\" enctype=\"multipart/form-data\">",
					$arResult["IBLOCK_CODE"], POST_FORM_ACTION_URI, "POST"
				).$res .= bitrix_sessid_post(),
				"isIblockTitle" => strlen( $arResult["IBLOCK_TITLE"] ) > 0 ? "Y" : "N",
				"isIblockDescription" => strlen( $arResult["IBLOCK_DESCRIPTION"] ) > 0 ? "Y" : "N",
				"isUseCaptcha" => $arParams["USE_CAPTCHA"] == "Y" ? "Y" : "N",
				"DATE_FORMAT" => CLang::GetDateFormat("SHORT"),
				"FORM_FOOTER" => "</form>"
			)
		);

		if( $arResult["isFormErrors"] == "Y" ){
			ob_start();
			ShowError( implode( '<br />', $arResult["FORM_ERRORS"] ) );
			$arResult["FORM_ERRORS_TEXT"] = ob_get_contents();
			ob_end_clean();
		}
		
		$arResult["SUBMIT_BUTTON"] = "<button class=\"".$arParams["SEND_BUTTON_CLASS"]."\" type=\"submit\">".$arParams["SEND_BUTTON_NAME"]."</button><br/>
									<input type=\"hidden\" name=\"form_submit\" value=\"".GetMessage("FORM_ADD")."\">";
		$arResult["CLOSE_BUTTON"] = "<button class=\"".$arParams["CLOSE_BUTTON_CLASS"]."\">".$arParams["CLOSE_BUTTON_NAME"]."</button>";
		$arResult["CAPTCHA_CAPTION"] = "<label for=\"captcha_word\">".GetMessage("FORM_CAPTCHA_FIELD_TITLE")."<span class=\"required-star\">*</span></label>";
		$arResult["CAPTCHA_IMAGE"] = "<input type=\"hidden\" name=\"captcha_sid\" class=\"captcha_sid\" value=\"".htmlspecialcharsbx($arResult["CAPTCHACode"])."\" /><img src=\"/bitrix/tools/captcha.php?captcha_sid=".htmlspecialcharsbx($arResult["CAPTCHACode"])."\" class=\"captcha_img\" width=\"180\" height=\"40\" />";
		$captcha_val = !empty( $_REQUEST["captcha_word"] ) ? $_REQUEST["captcha_word"] : "";
		$captcha_val = htmlspecialchars( $captcha_val );
		$arResult["CAPTCHA_FIELD"] = "<input type=\"text\" name=\"captcha_word\" value=\"".$captcha_val."\" class=\"form-control captcha required\" />".($captcha_error ? "<label for=\"captcha_word\" class=\"error\">".GetMessage("CAPTCHA_ERROR")."</label>" : "");
		$arResult["CAPTCHA_ERROR"] = $captcha_error ? "Y" : "N";
		
		$this->initComponentTemplate();
		if( !empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) || $_REQUEST["formresult"] == "ADDOK" || $arResult["isFormErrors"] == "Y" ){
			$APPLICATION->SetAdditionalCSS($this->__template->__folder.'/style.css');
			$APPLICATION->ShowCSS(true);
		}
		
		$this->IncludeComponentTemplate();
	}else{
		ShowError(GetMessage($arResult["ERROR"]));
	}
}else{
	ShowError(GetMessage("FORM_MODULE_NOT_INSTALLED"));
}?>