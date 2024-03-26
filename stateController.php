<?php

const PROCESS_FILE = 'updatedLeads.txt';
const KEY_SAVED = 'data';

/**
 * Помещает ассоциативный массив с обработанными сделками в файл
 * @param array $updatedArray ассоциативный массив сделок
 * @return void
 */
function putLeadToProcess(string|int $leadId): void {
    $array = getProcessedLeads();
    $array[] = $leadId;
    $newJsonData = serialize($array);
    file_put_contents(PROCESS_FILE, $newJsonData);
}

/**
 * Возвращает ассоциативный массив сделок прошедших обработку
 * @return array массив сделок прошедших обработку
 */
function getProcessedLeads(): array {
    $jsonData = file_get_contents(PROCESS_FILE);
    if(empty($jsonData)) {
        return array();
    }
    return unserialize($jsonData);
}

/**
 * Очищает файл с обработанными сделаками
 * @return void
 */
function removeProcessedLead($leadId): void {
    $array = getProcessedLeads();
    $array = array_reverse($array);
    $result = array_search($leadId, $array);
    unset($array[$result]);
    $newJsonData = serialize($array);
    file_put_contents(PROCESS_FILE, $newJsonData);
}

/**
 * Проверяет находится ли сделка в обновленном состояние
 * @param string|int $leadId идентификатор сделки
 * @return bool находится ли сделка в обновленном состояние
 */
function isLeadInProcessed(string|int $leadId): bool {
    $processedLeads = getProcessedLeads();
    if(empty($processedLeads)) {
       return false;
    }
    return in_array($leadId, $processedLeads);
}