<?php

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Client\LongLivedAccessToken;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Exceptions\AmoCRMMissedTokenException;
use AmoCRM\Exceptions\AmoCRMoAuthApiException;
use AmoCRM\Models\CustomFieldsValues\NumericCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NumericCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\NumericCustomFieldValueModel;

require 'vendor/autoload.php';
require 'stateController.php';
const AUTH_TOKEN = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjMyYWRiNzMzMDY1OWI3ZjU5MDRkYmFhZmVlMWY3ZjcyNjA4ZDZhYWMwODg5N2UyNmFlNGU2Mjc2OGQwYmY5ZDhlNmI0ZDQ3ZDUzMjhjMDM0In0.eyJhdWQiOiJlMTUwNTNlMy0zYTk1LTRlZjMtOWRiZS0xZDU0NTFmYzg1NTEiLCJqdGkiOiIzMmFkYjczMzA2NTliN2Y1OTA0ZGJhYWZlZTFmN2Y3MjYwOGQ2YWFjMDg4OTdlMjZhZTRlNjI3NjhkMGJmOWQ4ZTZiNGQ0N2Q1MzI4YzAzNCIsImlhdCI6MTcxMTQ0OTMwNCwibmJmIjoxNzExNDQ5MzA0LCJleHAiOjE3MjIzODQwMDAsInN1YiI6IjEwODU0NTg2IiwiZ3JhbnRfdHlwZSI6IiIsImFjY291bnRfaWQiOjMxNjU5MzEwLCJiYXNlX2RvbWFpbiI6ImFtb2NybS5ydSIsInZlcnNpb24iOjIsInNjb3BlcyI6WyJjcm0iLCJmaWxlcyIsImZpbGVzX2RlbGV0ZSIsIm5vdGlmaWNhdGlvbnMiLCJwdXNoX25vdGlmaWNhdGlvbnMiXSwiaGFzaF91dWlkIjoiN2Y5Y2FkMzQtZjJkNy00MGIyLThmZDYtZmY3ODZiOGMzMWY2In0.rGz813SuDLy6KYhWJl3M4Y-UiwX03hTWK5Q9go8XvHIJYnU6ofkzi7AG3LFsMqgJcA4I7IuEkdyFSO4zz2lAgCtP8LIEMox6wACP48D2OgGOxQNYJe6ekMm57Ez_8qtQo50cus1MemMxa2fJ4INu9-oNJV3HkCOMMEngnTJuhiIQXkpsQYWFKG2nzalQEY4qPVp9tp8UxuCncSPp_Exug3W6bPYHup4NcjmcAKwKnGo_NQI97bGcQmJs16CeUkOHl1pJgefrW4GG4XvQP1QYIyFitpFw4L6NdHgA_YDBAd-228tzKnhZUcPlvvrSEVJ7Xps5UyjXJAAYVVxBdoruWQ';
const costPriceId = 137719;
const incomeId = 137721;


$apiClient = new AmoCRMApiClient();
try {
    $longLivedAccessToken = new LongLivedAccessToken(AUTH_TOKEN);
} catch (\AmoCRM\Exceptions\InvalidArgumentException $e) {
    echo 'Аргумент неверного типа';
    return http_response_code(500);
}

$apiClient->setAccessToken($longLivedAccessToken)
    ->setAccountBaseDomain('njvesus.amocrm.ru');

try {
    $leadsService = $apiClient->leads();
} catch (AmoCRMMissedTokenException $e) {
    echo 'Запрос не может быть выполнен, потому что токен отсуствует';
    return http_response_code(500);
}

// Если пришел запрос POST
if(isset($_POST)) {
    $leadId = 0;
    if(isset($_POST['leads']['add'][0]['id'])) {
        $leadId = $_POST['leads']['add'][0]['id'];
    }
    else if(isset($_POST['leads']['update'][0]['id'])){
        $leadId = $_POST['leads']['update'][0]['id'];
    }
    else {
        echo 'Id of lead is not found';
        return http_response_code(400);
    }

    if(isLeadInProcessed($leadId)) {
        removeProcessedLead($leadId);
        return http_response_code(304);
    }

    try {
        $lead = $leadsService->getOne($leadId);
    } catch (AmoCRMoAuthApiException|AmoCRMApiException $e) {
        echo 'Failed to get lead';
        return http_response_code(500);
    }

    $budget = $lead->getPrice();
    $costPriceValue = 0;
    $customFields = $lead->getCustomFieldsValues();
    if(empty($customFields)) {
        $customFields = new CustomFieldsValuesCollection();
    }
    $costPriceField = $customFields->getBy('fieldId', costPriceId);
    // Если поле с себестоимостью найдено задаем значение
    if($costPriceField) {
        $costPriceValue = $costPriceField->getValues()->first()->getValue();
    }

    // Ищем поле прибыли
    $incomeField = $customFields->getBy('fieldId', incomeId);
    $incomeField = (new NumericCustomFieldValuesModel())->setFieldId(incomeId);
    $incomeFieldValueCollection = (new NumericCustomFieldValuesModel());

    // Задаем значение полю прибыль
    $incomeField->setValues(
        (new NumericCustomFieldValueCollection())
            ->add(
                (new NumericCustomFieldValueModel())
                    ->setValue($budget - $costPriceValue)
            )
    );
    // Добавляем поле прибыль
    $customFields->add($incomeField);
    $lead->setCustomFieldsValues($customFields);
    try {
        // Обновляем сделку
        $leadsService->updateOne($lead);
    } catch (AmoCRMApiException $e) {
        echo 'Failed to update';
        removeProcessedLead($leadId);
        return http_response_code(500);
    }
    // добавляем сделку в обновленные сделки и записываем в файл
    putLeadToProcess($leadId);



}