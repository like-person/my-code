<?php
/**
 * @copyright Copyright (c) 09.11.2023. ICL co
 * @author Damir.Gallyamov@icl-services.com
 */

/**
 * Class IblockElement
 * Используется для создания/обновления элементов в нескольких инфоблоках с синхронизацией с внешней системой MDP.
 *
 * @package ICL\MDP
 */

namespace ICL\MDP;

use CModule,
    Exception,
    Adv\Core\Utils,
    Bitrix\Highloadblock\HighloadBlockTable,
    Bitrix\Iblock\PropertyTable,
    Bitrix\Main\Config\Option,
    Bitrix\Main\Application;

class IblockElement
{
    /**
     * @var int
     */
    private int $elementIBID;
    /**
     * @var bool
     */
    private bool $mailing;
    /**
     * @var int
     */
    private int $campaignIBID;
    /**
     * @var string
     */
    private string $campaignLinkProp;

    /**
     * @param int $elementIBID
     * @param bool $mailing
     * @param string $campaignLinkProp
     * 
     */
    public function __construct(int $elementIBID, bool $mailing, string $campaignLinkProp) {
        CModule::IncludeModule("iblock");
        CModule::IncludeModule("workflow");
        CModule::IncludeModule('zentiva.onekey');
        $this->elementIBID = $elementIBID;
        $this->mailing = $mailing;
        $this->campaignLinkProp = $campaignLinkProp;
        $this->campaignIBID = Utils::getIBlockIdByCode("campaigns", "service");
    }
    /**
     * @param array $data
     * @param array $files
     * 
     * @return array
     * 
     */
    public function saveRequest(array $data = [], array $files = []): array
    {
        global $DB;
        $errors = [];

        $code_params = Array(
            "max_len" => "100", // обрезает символьный код до 100 символов
            "change_case" => "L", // буквы преобразуются к нижнему регистру
            "replace_space" => "-", // меняем пробелы на нижнее подчеркивание
            "replace_other" => "-", // меняем левые символы на нижнее подчеркивание
            "delete_repeat_replace" => "true", // удаляем повторяющиеся нижние подчеркивания
            "use_google" => "false" // отключаем использование google
        );

        $PROP_CAMPAIGN = [
            "CAMPAIGN_START" => $data["CAMPAIGN_START"],
            "CAMPAIGN_END" => $data["CAMPAIGN_END"],
            "FRANCHISE" => $data["FRANCHISE"],
            "FRANCHISE_MAIN" => $data["select2-FRANCHISE-chenge"] ?? $data["FRANCHISE_MAIN"],
            "GBU" => $data["GBU"],
            "GBU_MAIN" => $data["select2-GBU-chenge"] ?? $data["GBU_MAIN"],
            "ARCHETYPE" => $data["ARCHETYPE"],
            "BRANDING" => $data["BRANDING"],
            "PRODUCT" => $data["C_PRODUCT"],
            "PRODUCT_MAIN" => $data["select2-C_PRODUCT-chenge"] ?? $data["C_PRODUCT_MAIN"],
            "TERAPEUTIC_AREA" => $data["C_NOSOLOGY"] ?? $data["TERAPEUTIC_AREA"],
            "TERAPEUTIC_AREA_MAIN" => $data["select2-C_NOSOLOGY-chenge"] ?? $data["TERAPEUTIC_AREA_MAIN"],
            "CAMPAIGN_CODE" => $data["CAMPAIGN_CODE"],
            "PERSONA" => $data["C_PERSONA"],
        ];

        if(isset($data["STRATEGIC_IMPERATIVES"])) {
            $st_imp_all=[];
            foreach(self::getStratImps() as $val){
                $st_imp_all[$val["ID"]] = $val["CODE"];
            }
            $strat_imps = [];
            foreach($data["STRATEGIC_IMPERATIVES"] as $val){
                $strat_imps[] = $st_imp_all[$val];
            }
            $data["STRATEGIC_IMPERATIVES"] = $strat_imps;
            if($data["select2-STRATEGIC_IMPERATIVES-chenge"]) {
                $data["KEY_STRATEGIC_IMPERATIVES"] = $st_imp_all[$data["select2-STRATEGIC_IMPERATIVES-chenge"]];
            }
        }
        if(isset($data["BRANDING_MAILING"])) {
            $data["BRANDING"] = $data["BRANDING_MAILING"];
        }

        $resElementProps = PropertyTable::getList(['select' => ['ID', 'CODE'], 'filter' => ['=IBLOCK_ID' => $this->elementIBID, 'ACTIVE' => 'Y']]);
        $PROP_ELEMENT = [];
        while($rowElementProps = $resElementProps->fetch()) {
            if(!empty($data[$rowElementProps['CODE']])) {
                $PROP_ELEMENT[$rowElementProps['CODE']] = $data[$rowElementProps['CODE']];
            }
            if(!empty($files[$rowElementProps['CODE']])) {
                if(is_array($files[$rowElementProps['CODE']]["size"])) {
                    $fileValues = [];
                    for ($i = 0; $i < count($files[$rowElementProps['CODE']]["size"]); $i++) {
                        $fileValues[$i] = [
                            "name" => $files[$rowElementProps['CODE']]["name"][$i],
                            "full_path" => $files[$rowElementProps['CODE']]["full_path"][$i],
                            "type" => $files[$rowElementProps['CODE']]["type"][$i],
                            "tmp_name" => $files[$rowElementProps['CODE']]["tmp_name"][$i],
                            "error" => $files[$rowElementProps['CODE']]["error"][$i],
                            "size" => $files[$rowElementProps['CODE']]["size"][$i]
                        ];
                    }
                } else {
                    $fileValues = $files[$rowElementProps['CODE']];
                }
                $PROP_ELEMENT[$rowElementProps['CODE']] = $fileValues;
            }
            if (in_array($rowElementProps['CODE'], ["PRODUCT_MAIN", "TERAPEUTIC_AREA_MAIN"])) {
                $PROP_ELEMENT[$rowElementProps['CODE']] = $PROP_CAMPAIGN[$rowElementProps['CODE']];
            }
            if ($rowElementProps['CODE'] == 'NOZOLOGIYA' && !$data[$rowElementProps['CODE']]) {
                $PROP_ELEMENT[$rowElementProps['CODE']] = $PROP_CAMPAIGN['TERAPEUTIC_AREA'];
            }
        }
        $element = new \CIBlockElement;

        $DB->StartTransaction();

        if($this->mailing) {
            $PROP_ELEMENT["DESCRIPTION_LETTER_REQUEST"] = [];
            // составное свойство
            $promo_page_counter = 0;
            while($data["URL_PROMO_PAGE_".$promo_page_counter] && $data["URL_PROMO_MATS_".$promo_page_counter]){
                $PROP_ELEMENT["DESCRIPTION_LETTER_REQUEST"][] = [
                    "SUBPROP_VALUES" => [
                        "ID_EMAIL_REQUEST" => $data["ID_EMAIL_REQUEST_".$promo_page_counter],
                        "URL_PROMO_PAGE" => $data["URL_PROMO_PAGE_".$promo_page_counter],
                        "URL_TYPE" => $data["URL_TYPE_".$promo_page_counter],
                        "URL_PROMO_MATS" => $data["URL_PROMO_MATS_".$promo_page_counter],
                        "OFFER_CODE" => $data["OFFER_CODE_".$promo_page_counter],
                        "URL_DESCRIPTION" => $data["URL_DESCRIPTION_".$promo_page_counter],
                        "COMMENT_EMAIL_REQUEST" => $data["COMMENT_EMAIL_REQUEST_".$promo_page_counter]
                    ]
                ];
                $promo_page_counter++;
            }
        }
        $arElementFields = [
            "IBLOCK_ID" => $this->elementIBID,
            "NAME" => $data["ELEMENT_NAME"],
            "CODE" => ($data["CODE"] ?? \CUtil::translit($data["ELEMENT_NAME"], "ru" , $code_params)),
            "PROPERTY_VALUES" => $PROP_ELEMENT
        ];
        if(isset($data['ACTIVE'])) {
            $arElementFields['ACTIVE'] = $data['ACTIVE'];
        }
        if($data["CONTENT_ELEM_END"]) {
            $arElementFields["DATE_ACTIVE_TO"] = $data["CONTENT_ELEM_END"];
        }
        if($data["ACTIVE_FROM"]) {
            $arElementFields["ACTIVE_FROM"] = $data["ACTIVE_FROM"];
        }
        if($data["URL_DESCRIPTION_C_ELEM"]) {
            $arElementFields["DETAIL_TEXT"] = $data["URL_DESCRIPTION_C_ELEM"];
            $arElementFields["DETAIL_TEXT_TYPE"] = "html";
        }
        if($data["CONTENT_PUBLISH"]) {
            $arElementFields['ACTIVE'] = $data["CONTENT_PUBLISH"];
        }
        if($data["ID"]) {
            $elementId = $data["ID"];
            foreach ($files as $propCode => $propValue) {
                if($PROP_ELEMENT[$propCode] && is_array($PROP_ELEMENT[$propCode])) {
                    $res = \CIBlockElement::GetProperty($this->elementIBID, $elementId, [], ['CODE'=>$propCode]);
                    while ($ar_props = $res->getNext()) {
                        if($ar_props["MULTIPLE"] == "Y") {
                            $arElementFields["PROPERTY_VALUES"][$propCode][$ar_props["PROPERTY_VALUE_ID"]] = ['del'=>'Y'];
                        }
                    }
                }
            }
            if(!$element->Update((int)$elementId, $arElementFields, false)){
                $errors[]['NAME'] = "Не удалось обновить запись (".$element->LAST_ERROR.")";
            }
        } else {
            $elementId = $element->Add($arElementFields, false);
            if(!$elementId) {
                $errors[]['NAME'] = "Не удалось создать запись (".$element->LAST_ERROR.")";
            } else {
                if($data['CONTENT_REQUEST_ID']) {
                    $request_id_wf = \CIBlockElement::WF_GetLast($data['CONTENT_REQUEST_ID']);
                    \CIBlockElement::SetPropertyValuesEx($data['CONTENT_REQUEST_ID'], false, ['CONTENT_ID' => $elementId]);
                    \CIBlockElement::SetPropertyValuesEx($request_id_wf, false, ['CONTENT_ID' => $elementId]);
                }
                if($data['EVENT_REQUEST_ID']) {
                    $request_id_wf = \CIBlockElement::WF_GetLast($data['EVENT_REQUEST_ID']);
                    \CIBlockElement::SetPropertyValuesEx($data['EVENT_REQUEST_ID'], false, ['EVENT_ID' => $elementId]);
                    \CIBlockElement::SetPropertyValuesEx($request_id_wf, false, ['EVENT_ID' => $elementId]);
                }
            }
        }
        if (count($errors) == 0) {
            $arFieldsMailingMDP = $arElementFields;
            $tacticType = '';
            if($data["CONTENT_IBLOCK_CODE"]) {
                $arFieldsMailingMDP["CONTENT_IBLOCK_CODE"] = $data["CONTENT_IBLOCK_CODE"];
                if($data["CONTENT_IBLOCK_CODE"] == 'events') {
                    $tacticType = 'webinar';
                } else {
                    $tacticType = 'content';
                }
            }
            foreach ($files as $propCode => $propValue) {
                unset($arFieldsMailingMDP["PROPERTY_VALUES"][$propCode]);
            }
            if (!empty($PROP_ELEMENT['PERSONA'])) {
                $PROP_CAMPAIGN["PERSONA"] = array_unique(array_merge((array)$PROP_ELEMENT['PERSONA'], (array)$PROP_CAMPAIGN["PERSONA"]));
            }
            $moduleEnabled = Option::get('icl.mdp', 'api_mdp_enabled');
            if($data["CAMPAIGN"] != "new"){ //update

                $ibCMRes = \CIBlockElement::GetList(
                    [],
                    ['IBLOCK_ID' => $this->campaignIBID, 'ID' => $data["CAMPAIGN"]],
                    false,
                    false,
                    ["IBLOCK_ID", "ID", "NAME"]
                );
                while($ob = $ibCMRes->GetNextElement()) {
                    $arFields = $ob->GetFields();
                    $arProps = $ob->GetProperties();
                    $PROP_CAMPAIGN[$this->campaignLinkProp] = is_array($arProps[$this->campaignLinkProp]["VALUE"]) ? $arProps[$this->campaignLinkProp]["VALUE"] : [];
                    if(!in_array($elementId, $PROP_CAMPAIGN[$this->campaignLinkProp])){
                        $PROP_CAMPAIGN[$this->campaignLinkProp][] = $elementId;
                    }
                    if(!empty((array)$arProps["PERSONA"]["VALUE"])) {
                        $PROP_CAMPAIGN["PERSONA"] = array_unique(array_merge((array)$arProps["PERSONA"]["VALUE"], (array)$PROP_CAMPAIGN["PERSONA"]));
                    }
                    $sendRequestToMdp = false;
                    foreach ($arProps as $code => $prop) {
                        if($prop["VALUE_ENUM_ID"]) {
                            $prop["VALUE"] = $prop["VALUE_ENUM_ID"];
                        }
                        if(!isset($PROP_CAMPAIGN[$code])) {
                            $PROP_CAMPAIGN[$code] = $prop["VALUE"];
                        } else {
                            if(in_array($code, Campaign::$campaignMdpProps)) {
                                if($PROP_CAMPAIGN[$code] != $prop["VALUE"]) {
                                    $sendRequestToMdp = true;
                                }
                            }
                        }
                    }
                    $arFieldsCampaign = [
                        "IBLOCK_ID" => $this->campaignIBID,
                        "NAME" => $data["NAME"],
                        "PROPERTY_VALUES" => $PROP_CAMPAIGN
                    ];

                    $code = \CUtil::translit($data["NAME"], "ru" , $code_params);
                    $arFieldsCampaign["CODE"] = $code . "_" . str_replace(".","-",$PROP_CAMPAIGN["CAMPAIGN_START"]);
                    if ($moduleEnabled == 'Y') {
                        if ($sendRequestToMdp) {
                            $resMdp = Main::updateMDP($arFields["ID"], $arFieldsCampaign, new (Main::CAMPAGIN)());
                        } else {
                            //не слать запрос в МДП в случае
                            $resMdp = [
                                'formid' => $arProps["MDP_FORM_ID"]['VALUE'],
                                'campaignid' => $arProps["MDP_CAMPAIDN_ID"]['VALUE'],
                                'campaigncode' => $arProps["CAMPAIGN_CODE"]['VALUE'],
                            ];
                        }
                        if (empty($resMdp['errors'])) {
                            $arFieldsCampaign["PROPERTY_VALUES"]["MDP_FORM_ID"] = $resMdp["formid"];
                            $arFieldsCampaign["PROPERTY_VALUES"]["MDP_CAMPAIDN_ID"] = $resMdp["campaignid"];
                            if (!empty($resMdp["campaigncode"])) {
                                $arFieldsCampaign["PROPERTY_VALUES"]["CAMPAIGN_CODE"] = $resMdp["campaigncode"];
                                $arFieldsMailingMDP["MDP_CAMPAIGN_ID"] = $arFieldsCampaign["PROPERTY_VALUES"]["MDP_CAMPAIDN_ID"];

                                if ($this->mailing || $data["CONTENT_IBLOCK_CODE"]) {
                                    $resMdpT = Main::updateMDP($elementId, $arFieldsMailingMDP, new (Main::TACTIC)($tacticType), true);
                                    if (empty($resMdpT['errors'])) {
                                        $arFieldsMailingMDP['PROPERTY_VALUES']['EXPOSURE_CODE'] = $resMdpT['exposurecode'];
                                        if (count($resMdpT['tacticOffers']) > 0) {
                                            if ($arFieldsMailingMDP["CONTENT_IBLOCK_CODE"]) {
                                                $arFieldsMailingMDP['PROPERTY_VALUES']['OFFER_CODE'] = $resMdpT['tacticOffers'][0];
                                            } else {
                                                $offerN = 0;
                                                foreach ($arFieldsMailingMDP['PROPERTY_VALUES']['DESCRIPTION_LETTER_REQUEST'] as &$prop) {
                                                    $prop['SUBPROP_VALUES']['OFFER_CODE'] = $resMdpT['tacticOffers'][$offerN];
                                                    $offerN++;
                                                }
                                            }
                                        }
                                        unset($arFieldsMailingMDP["CODE"]);
                                        Application::getInstance()->getSession()->set('SKIP_EVENTS', 'Y');
                                        if (!$element->Update((int)$elementId, $arFieldsMailingMDP, false)) {
                                            $errors[]['NAME'] = "Не удалось обновить запись (" . $element->LAST_ERROR . ")";
                                        } else {
                                            if ($resMdpT["approved"]) {
                                                $propAppr = \CIBlockPropertyEnum::GetList(
                                                    [],
                                                    array("IBLOCK_ID" => $this->campaignIBID, "XML_ID" => "yes", "CODE" => "APPROVED")
                                                )->Fetch();
                                                $arFieldsCampaign["PROPERTY_VALUES"]["APPROVED"] = $propAppr["ID"];
                                            } else {
                                                $arFieldsCampaign["PROPERTY_VALUES"]["APPROVED"] = "";
                                            }
                                        }
                                        Application::getInstance()->getSession()->set('SKIP_EVENTS', 'N');
                                    } else {
                                        $errors = array_values(array_unique($resMdpT['errors'], SORT_REGULAR));
                                    }
                                }
                            } else {
                                $errors = array_values(array_unique($resMdp['errors'], SORT_REGULAR));
                            }
                        }
                    }
                    if(count($errors) == 0) {
                        if(!$element->Update((int)$data["CAMPAIGN"], $arFieldsCampaign, false)){
                            $errors[]['NAME'] = "Не удалось обновить кампанию (".$element->LAST_ERROR.")";
                        }
                    }
                }
            } else { //new

                $PROP_CAMPAIGN[$this->campaignLinkProp] = [$elementId];

                $arFieldsCampaign = [
                    "IBLOCK_ID" => $this->campaignIBID,
                    "NAME" => $data["NAME"],
                    "PROPERTY_VALUES" => $PROP_CAMPAIGN
                ];

                $code = \CUtil::translit($data["NAME"], "ru" , $code_params);
                $arFieldsCampaign["CODE"] = $code . "_" . str_replace(".","-",$PROP_CAMPAIGN["CAMPAIGN_START"]);
                if($moduleEnabled == 'Y') {
                    $resMdp = Main::createMDP($arFieldsCampaign, new (Main::CAMPAGIN)());

                    if (empty($resMdp['errors'])) {
                        $arFieldsCampaign["PROPERTY_VALUES"]["MDP_FORM_ID"] = $resMdp["formid"];
                        $arFieldsCampaign["PROPERTY_VALUES"]["MDP_CAMPAIDN_ID"] = $resMdp["campaignid"];
                        $arFieldsCampaign["PROPERTY_VALUES"]["CAMPAIGN_CODE"] = $resMdp["campaigncode"];

                        if($this->mailing || $data["CONTENT_IBLOCK_CODE"]) {
                            $arFieldsMailingMDP["MDP_CAMPAIGN_ID"] = $arFieldsCampaign["PROPERTY_VALUES"]["MDP_CAMPAIDN_ID"];

                        $resMdpT = Main::createMDP( $arFieldsMailingMDP, new (Main::TACTIC)($tacticType), true);

                            if (empty($resMdpT['errors'])) {
                                $arFieldsMailingMDP['PROPERTY_VALUES']['EXPOSURE_CODE'] = $resMdpT['exposurecode'];
                                if(count($resMdpT['tacticOffers']) > 0) {
                                    if($arFieldsMailingMDP["CONTENT_IBLOCK_CODE"]) {
                                        $arFieldsMailingMDP['PROPERTY_VALUES']['OFFER_CODE'] = $resMdpT['tacticOffers'][0];
                                    } else {
                                        $offerN = 0;
                                        foreach ($arFieldsMailingMDP['PROPERTY_VALUES']['DESCRIPTION_LETTER_REQUEST'] as &$prop) {
                                            $prop['SUBPROP_VALUES']['OFFER_CODE'] = $resMdpT['tacticOffers'][$offerN];
                                            $offerN ++;
                                        }
                                    }
                                }
                                Application::getInstance()->getSession()->set('SKIP_EVENTS', 'Y');
                                foreach ($files as $propCode => $propValue) {
                                    unset($arFieldsMailingMDP[$propCode]);
                                }
                                if(!$element->Update((int)$elementId, $arFieldsMailingMDP, false)) {
                                    $errors[]['NAME'] = "Не удалось обновить запись (".$element->LAST_ERROR.")";
                                } else {
                                    if ($resMdpT["approved"]) {
                                        $propAppr = \CIBlockPropertyEnum::GetList(
                                            [],
                                            array("IBLOCK_ID" => $this->campaignIBID, "XML_ID" => "yes", "CODE" => "APPROVED")
                                        )->Fetch();
                                        $arFieldsCampaign["PROPERTY_VALUES"]["APPROVED"] = $propAppr["ID"];
                                    } else {
                                        $arFieldsCampaign["PROPERTY_VALUES"]["APPROVED"] = "";
                                    }
                                }
                                Application::getInstance()->getSession()->set('SKIP_EVENTS', 'N');
                            } else {
                                $errors = array_values(array_unique($resMdpT['errors'], SORT_REGULAR));
                            }
                        }
                    } else {
                        $errors = array_values(array_unique($resMdp['errors'], SORT_REGULAR));
                    }
                }
                if(count($errors) == 0) {
                    if(!$element->Add($arFieldsCampaign, false)) {
                        $errors[]['NAME'] = "Не удалось создать кампанию (".$element->LAST_ERROR.")";
                    }
                }
            }
        }
        if(count($errors) > 0) {
            $DB->Rollback();
        } else {
            $DB->Commit();
        }
        return $errors;
    }

    /**
     * @return array
     * 
     */
    public static function getStratImps(): array
    {
        $hliblock_id = (int)HighloadBlockTable::getList(['filter' => ["=NAME" => "KeyMessages"]])->fetch()["ID"];
        $hlblock = HighloadBlockTable::getById($hliblock_id)->fetch();
        $entity = HighloadBlockTable::compileEntity($hlblock);
        $entity_data_class = $entity->getDataClass();
        $rsData = $entity_data_class::getList(array(
            "select" => array("*"),
            "order" => array("UF_NAME" => "ASC"),
            "filter" => array()
        ));
        $arStrIms = [];
        while($arData = $rsData->Fetch())
        {
            $arStrIms[] = [
                "ID" => $arData["ID"],
                "NAME" => $arData["UF_NAME"],
                "CODE" => $arData["UF_XML_ID"],
                "UF_NAME_RU" => $arData["UF_NAME_RU"]
            ];
        }
        return $arStrIms;
    }

    /**
     * @param string $HBName
     * @param array $addFields
     * @param array $filter
     * 
     * @return array
     * 
     */
    public static function getHBList(string $HBName, array $addFields = [], array $filter = []): array
    {
        $hliblock_id = (int)HighloadBlockTable::getList(['filter' => ["=NAME" => $HBName]])->fetch()["ID"];
        $hlblock = HighloadBlockTable::getById($hliblock_id)->fetch();
        $entity = HighloadBlockTable::compileEntity($hlblock);
        $entity_data_class = $entity->getDataClass();
        $rsData = $entity_data_class::getList(array(
            "select" => array("*"),
            "order" => array("UF_NAME" => "ASC"),
            "filter" => $filter,
        ));
        $outList = [];
        while($arData = $rsData->Fetch())
        {
            $outList[$arData["UF_XML_ID"]] = [
                "ID" => $arData["ID"],
                "NAME" => $arData["UF_NAME"],
                "CODE" => $arData["UF_XML_ID"],
            ];
            foreach ($addFields as $field) {
                $outList[$arData["UF_XML_ID"]][$field] = $arData[$field];
            }
        }
        return $outList;
    }
}

