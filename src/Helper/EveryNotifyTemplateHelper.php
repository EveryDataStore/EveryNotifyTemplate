<?php

namespace EveryNotifyTemplate\Helper;

use EveryDataStore\Helper\EveryDataStoreHelper;
use EveryDataStore\Model\RecordSet\RecordSetItem;
use SilverStripe\Core\Config\Config;

/** EveryDataStore/EveryNotifyTemplate v1.0
 * 
 * This class implements functions related to creating template folder path, 
 * filling in template values, translating labels and converting template extension
 */
class EveryNotifyTemplateHelper extends EveryDataStoreHelper {

    /**
     * This function fills in the template with the corresponding data values
     * 
     * @param string $template
     * @param DataObject $recordSetItem
     */
    public static function setTemplateVars4RecordSetItem($template, $recordSetItem = false) {
        $recordSetName = $recordSetItem->RecordSet()->Title;;
        $template = str_replace('<table>', '<table width="100%">', $template);
        $template = self::replaceImg($template);
        $template = self::translate($template); 
        $template = self::replaceConfVar($template); 
        $template = self::replaceRecordItemData($template, $recordSetItem, $recordSetName);
        $footer = self::getStringBetween($template, '{{footer}}', '{{/footer}}');
        $header = self::getStringBetween($template, '{{header}}', '{{/header}}');
        $template = str_replace([$header, '{{header}}','{{/header}}', $footer, '{{footer}}','{{/footer}}'], "", $template);
        //$template = str_replace('<p></p>','',$template);
        $template = $recordSetItem->RecordItems()->Count() > 0 ? self::replaceLoopItems($template, $recordSetItem->RecordItems()): $template;
        return self::HTML2PDF($template, $header, $footer);
    }

    /**
     * This function translates HTML template page into PDF format
     * 
     * @param string $html
     * @return string
     */
    public static function HTML2PDF($body, $header = null, $footer = null) {
        $tmpDirPath = self::getTmpDirPath();
        $tmpfilePath = $tmpDirPath . self::getRandomString(10) . '.pdf';
        $fOpen = fopen($tmpfilePath, 'w');

        if ($fOpen) {
            $customConfig = Config::inst()->get('Mpdf') ? Config::inst()->get('Mpdf'): [];
            $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
            $fontDirs = $defaultConfig['fontDir'];
            $fontDirName = isset($customConfig['font_dir_name']) ? $customConfig['font_dir_name']: 'inter';
            $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
            $fontData = $defaultFontConfig['fontdata'];

            $mpdf = new \Mpdf\Mpdf([
                'tempDir' => $tmpDirPath,
                'mode' => isset($customConfig['mode']) ? $customConfig['mode']: 'utf-8', 
                'format' => isset($customConfig['format']) ? $customConfig['format']: 'A4-L', 
                'orientation' => isset($customConfig['orientation']) ? $customConfig['orientation']: 'L',
                'fontDir' => array_merge($fontDirs, [
                    $_SERVER['DOCUMENT_ROOT'].'/everynotifytemplate/fonts/'.$fontDirName,
                ]),
                'fontdata' => $fontData + [
                    'inter' => [
                        'R' => isset($customConfig['font_R']) ? $customConfig['font_R']: 'Inter-Regular.ttf',
                        'I' => isset($customConfig['font_I']) ? $customConfig['font_I']:  'Inter-Light.ttf',
                    ]
                ],
                'default_font' => $fontDirName
            ]);
            $mpdf->SetMargins(15, 15, 15);
            if($header){
                $mpdf->autoScriptToLang = true;
                $mpdf->autoLangToFont = true;
                $mpdf->SetHTMLHeader($header);
            }
            
            if($footer){
                $mpdf->SetHTMLFooter($footer);
            }
            
            $mpdf->WriteHTML($body);
            $mpdf->Output();
            return $tmpfilePath;
        }
    }
    
    /**
     * This function returns an array of template values that match regex $pattern 
     * without 'loop' string
     * 
     * @param string $template
     * @return array
     */
    private static function getLoopNames($template) {
        $pattern = "/\{{loop ([A-Za-z\/]+)\}}/";
        preg_match_all($pattern, $template, $matches);
        $loopNames = [];
        if (count($matches) > 0) {
            foreach ($matches as $matche) {
                if (is_array($matche)) {
                    foreach ($matche as $m) {
                        if (strpos($m, 'loop') === false) {
                            $loopNames[] = $m;
                        }
                    }
                }
            }
        }
        return $loopNames;
    }
    
    /**
     * 
     * @param string $template
     * @param DataObject $recordSetItem
     * @param type $recordSetName
     */
    private static function replaceRecordItemData($template, $recordSetItem, $recordSetName) {
        if ($recordSetItem->ItemData()->Count() > 0) {
            $i = 0;
            foreach ($recordSetItem->ItemData() as $data) {
                $labelPattern = '{{' . $recordSetName . '.' . $data->FormField()->getLabel() . '}}';
                $template = str_replace($labelPattern, nl2br($data->Value()), $template);
                if ($data->FormField()->getTypeSlug() == 'relationfield' && ($data->FormField()->getRelationFieldType() == 'HasOne' || $data->FormField()->getRelationFieldType() == 'FieldMapping')) {
                    $recordSetItemDataValue = is_array(unserialize($data->Value)) ? unserialize($data->Value) : $data->Value;
                    $hoRecordSetItems = RecordSetItem::get()->filter(['Slug' => $recordSetItemDataValue]);
                    if ($hoRecordSetItems) {
                        foreach ($hoRecordSetItems as $hoRecordSetItem) {
                            foreach ($hoRecordSetItem->ItemData() as $hoData) {
                                $hoLabelPattern = '{{'. $hoRecordSetItem->RecordSet()->Title . '.' . $hoData->FormField()->getLabel() . '}}';
                                $template = str_replace($hoLabelPattern, nl2br($hoData->Value()), $template);
                            }
                        }
                    }
                }
                $i++;
            }
        }
        return $template;
    }
    
    /**
     * This function constructs array of different template lines 
     * 
     * @param string $template
     * @param array $recordSetItems
     */
    private static function replaceLoopItems($template, $recordSetItems) {
        $loopElements = self::getLoopHTMLElements($template);
        $baseRows = [];
        $i = 1;
        foreach ($recordSetItems->Sort('ID ASC') as $item) {
            $recordSetName = $item->RecordSet()->Title;
            $recordSetElements = isset($loopElements[$recordSetName]) ? $loopElements[$recordSetName] : null;
            $recordSetElementsBody = isset($recordSetElements['tbody']) ? $recordSetElements['tbody'] : null;
            $baseRow = self::getStringBetween($recordSetElementsBody, '<tr>', '</tr>');
            $baseRows[] = $baseRow;
            $row = $baseRow ;
            $rowVars = self::getRowVars($row);
            if($row && $item->ItemData()->Count() > 0) {
                $rowVarContent = self::getRowVarContent($rowVars, $item->ItemData());
                $search = [];
                $replace = [];
                foreach($rowVarContent as $content){
                    if(!empty($content['var']) && !empty($content['val'])){
                        $search[] = "{{".trim($content['var'])."}}";
                        $replace[] = $content['val'];
                    }
                }
                $row = str_replace($search,$replace, $row);
            }
            
            $row = str_replace("pos",$i, $row);
            $template = str_replace($baseRow,'<tr>'.$row.'</tr>'.'<tr>'.$baseRow.'</tr>', $template); 
         $i++;
        }
        
        foreach($baseRows as $baseRow){
            $template = str_replace('<tr>'.$baseRow.'</tr>','', $template); 
        }
        
       $template  = self::clearLoopElements($template);
       return self::clearLoopElements($template);
    }
    
    /**
     * This function returns the HTML elements of loop
     * @param string $template
     * @param string $loopName
     * @return array
     */
    private static function getLoopHTMLElements($template) {
        $loopNames = self::getLoopNames($template);
        $elements = [];
        if ($loopNames) {
            foreach ($loopNames as $loopName) {
                $table = self::getStringBetween($template, '{{loop ' . $loopName . '}}', '{{/loop ' . $loopName . '}}');
                if ($table) {
                    $elements[$loopName] = [
                        'thead' => self::getStringBetween($table, '<thead>', '</thead>'),
                        'tfoot' => self::getStringBetween($table, '<tfoot>', '</tfoot>'),
                        'tbody' => self::getStringBetween($table, '<tbody>', '</tbody>')
                    ];
                }
            }
        }
        return $elements;
    }
    
    /**
     *  This function returns all vars in a row
     * @param string $row
     * @return array
     */
    private static function getRowVars($row){
        $vars = [];
        foreach(explode("}}", $row) as $r){
            $vars[] = str_replace("{{", '', $r);
        }
        return $vars;
    }
    
    /**
     * This function returns row vars and values
     * @param array $vars
     * @param type $itemData
     * @return array
     */
    private static function getRowVarContent($vars, $itemData) {
        $content = [];
        foreach ($vars as $var) {
            $niceVar = trim(str_replace(['&nbsp;', ' '], '', strip_tags($var)));
            $splitVar = explode('.', $var);
            $varName = end($splitVar);
            foreach ($itemData->Sort('RecordSetItemID ASC') as $data) {
                if ($data->FormField()->getLabel() == $varName && !in_array($niceVar, $content)) {
                    $content[$niceVar] = ['var' => $niceVar, 'val' => $data->Value()];
                }
            }

            if (!isset($content[$niceVar])) {
                $content[$niceVar] = ['var' => $niceVar, 'val' => $niceVar];
            }
        }
        return $content;
    }

    /**
     * This function creates a folder under specified path if it does not exist
     * 
     * @return string
     */
    private static function getTmpDirPath() {
        $tmpPath = $_SERVER['DOCUMENT_ROOT'] . '/tmp/';

        $folderPath = $tmpPath . self::getCurrentDataStore()->Title . '/print/' . date("Ymdhis") . '/';
        if (!is_dir($tmpPath . self::getCurrentDataStore()->Title)) {
            mkdir($tmpPath . self::getCurrentDataStore()->Title, 0777, true);
        }

        if (!is_dir($tmpPath . self::getCurrentDataStore()->Title . '/print/')) {
            mkdir($tmpPath . self::getCurrentDataStore()->Title . '/print/', 0777, true);
        }

        if (!is_dir($folderPath)) {
            mkdir($folderPath, 0777, true);
        }

        return $folderPath;
    }

    /**
     * This function empties $loopNames array
     * 
     * @param string $template
     * @param array $loopNames
     * @return string
     */
    private static function clearLoopElements($template) {
        $loopNames = self::getLoopNames($template);
        $search = [];
        foreach ($loopNames as $lo) {
            $template = str_replace(['{{loop ' . $lo . '}}', '{{/loop ' . $lo . '}}'], ['', ''], $template);
            preg_match_all('/\{{' . $lo . '.([A-Za-z0-9\/]+)\}}/', $template, $matches);
            if ($matches) {
                foreach ($matches as $match) {
                    if ($match) {
                        foreach ($match as $m) {
                            if (strpos($m, "{{") !== false) {
                               $search[] = $m;
                            }
                        }
                    }
                }
            }
        }
        preg_match('~{{(.*?)}}~', $template, $matches2);
        foreach($matches2 as $match2){
           if(strpos($match2, "}}") !== false){
                $search[] = $match2;
           }
        }
        $search[] = '<p><br></p>';
        $search[] = '<p></p>';
        return str_replace($search, "", $template);

    }

    /**
     * This function translates labels in the template
     * @param string $template
     * @return string
     */
    private static function translate($template) {
        $result = self::getAllStringBetween($template, "{{t_", "}}");
        if ($result) {
            foreach ($result as $k => $v) {
                $template = str_replace('{{t_' . $v . '}}', self::_t($v), $template);
            }
        }
       return $template;
    }
    
    /**
     * This function replace img var with img tag 
     * @param type $template
     * @return string
     */
    private static function replaceImg($template){
        $lines = explode('}}', $template);
        $search = [];
        $replace = [];
        foreach($lines as $line){
            if (strpos($line, '{{img') !== false) {
                $newLine = str_replace('{{img', '<img ', $line).' />';
                $search[] = $line.'}}';
                $replace[] = $newLine;
            }
        }
        
        return $search && $replace ? str_replace($search, $replace, $template) : $template;
    }
    /**
     * This function replace conf var with value in template
     * @param string $template
     * @return string
     */
    private static function replaceConfVar($template){
        $matches = self::getConfMatches($template);
        if($matches){
           $conf_vars =  self::getConfVars($matches);

           if($conf_vars){
               $search = [];
               $replace = [];
               foreach($conf_vars as $k => $v){
                   $search[] = '{{'.$k.'}}';
                   $replace[] = $v;
               }
               return str_replace($search, $replace, $template);
           }
        }
        return $template;
    }
    
    /**
     * Finds conf var names in template.
     * @param string $template
     * @return array
     */
    private static function getConfMatches($template) {
        $matches = [];
        preg_match_all('/{{(.*?)}}/', $template, $match);
        foreach ($match[0] as $m) {
            $nice_m = str_replace(['{{', '}}', '/'], ['', '', ''], $m);
            if (is_string($nice_m) && strpos($nice_m, 'conf.') !== false && !in_array($nice_m, $matches)) {
                $matches[] = $nice_m;
            }
        }
        return $matches;
    }

    /**
     * This function returns conf var value 
     * @param array $matches
     */
    private static function getConfVars($matches) {
        $vars = [];
        foreach ($matches as $v) {
                $nice_m = str_replace('conf.', '', $v);
                if (strpos($nice_m, '.')) {
                    $split_m = explode('.', $nice_m);
                    $confVar = self::getEveryConfig($split_m[0]);
                    if ($confVar) {
                        $vars[$v] = isset($confVar[$split_m[1]]) ? $confVar[$split_m[1]] : null;
                    }
                } else {
                    $confVar = self::getEveryConfig($nice_m);
                    if ($confVar) {
                        $vars[$v] = isset($confVar[$nice_m]) ? confVar[$nice_m]: null;
                    }
                }
        }
        return $vars;
    }
    
    private static function debug($out) {
        echo '<textare rows="30" cols="50"><td>'.$out.'</td></textarea>';
    }
}
