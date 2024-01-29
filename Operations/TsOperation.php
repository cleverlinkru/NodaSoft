<?php

namespace NW\WebService\References\Operations\Notification\Operations;

class TsOperation extends Operation
{
    public const TYPE_NEW    = 1;
    public const TYPE_CHANGE = 2;

    /**
     * @throws \Exception
     */
    public function doOperation(): void
    {
        $data = (array)$this->getRequest('data');
        $resellerId = $data['resellerId'];
        $notificationType = (int)$data['notificationType'];
        $result = [
            'notificationEmployeeByEmail' => false,
            'notificationClientByEmail'   => false,
            'notificationClientBySms'     => [
                'isSent'  => false,
                'message' => '',
            ],
        ];

        if (empty((int)$resellerId)) {
            $result['notificationClientBySms']['message'] = 'Empty resellerId';
            return $result;
        }

        if (empty((int)$notificationType)) {
            throw new \Exception('Empty notificationType', 400);
        }

        $reseller = Contractor::getInstanceById((int)$resellerId);
        if ($reseller === null) {
            throw new \Exception('Seller not found!', 400);
        }

        $client = Contractor::getInstanceById((int)$data['clientId']);
        if ($client === null || $client->type !== Contractor::TYPE_CUSTOMER || $client->Seller->id !== $resellerId) {
            throw new \Exception('сlient not found!', 400);
        }

        $cFullName = $client->name ? $client->getFullName() : $client->name;

        $cr = Contractor::getInstanceById((int)$data['creatorId']);
        if ($cr === null) {
            throw new \Exception('Creator not found!', 400);
        }

        $et = Contractor::getInstanceById((int)$data['expertId']);
        if ($et === null) {
            throw new \Exception('Expert not found!', 400);
        }

        $differences = '';
        if ($notificationType === self::TYPE_NEW) {
            $differences = __('NewPositionAdded', null, $resellerId);
        } elseif ($notificationType === self::TYPE_CHANGE && !empty($data['differences'])) {
            $differences = __('PositionStatusHasChanged', [
                    'FROM' => Status::getName((int)$data['differences']['from']),
                    'TO'   => Status::getName((int)$data['differences']['to']),
                ], $resellerId);
        }

        $templateData = [
            'COMPLAINT_ID'       => (int)$data['complaintId'],
            'COMPLAINT_NUMBER'   => (string)$data['complaintNumber'],
            'CREATOR_ID'         => (int)$data['creatorId'],
            'CREATOR_NAME'       => $cr->getFullName(),
            'EXPERT_ID'          => (int)$data['expertId'],
            'EXPERT_NAME'        => $et->getFullName(),
            'CLIENT_ID'          => (int)$data['clientId'],
            'CLIENT_NAME'        => $cFullName,
            'CONSUMPTION_ID'     => (int)$data['consumptionId'],
            'CONSUMPTION_NUMBER' => (string)$data['consumptionNumber'],
            'AGREEMENT_NUMBER'   => (string)$data['agreementNumber'],
            'DATE'               => (string)$data['date'],
            'DIFFERENCES'        => $differences,
        ];

        // Если хоть одна переменная для шаблона не задана, то не отправляем уведомления
        foreach ($templateData as $key => $tempData) {
            if (empty($tempData)) {
                throw new \Exception("Template Data ({$key}) is empty!", 500);
            }
        }

        $emailFrom = $this->getResellerEmailFrom($resellerId);
        // Получаем email сотрудников из настроек
        $emails = $this->getEmailsByPermit($resellerId, 'tsGoodsReturn');
        $subject = __('complaintEmployeeEmailSubject', $templateData, $resellerId);
        $body = __('complaintEmployeeEmailBody', $templateData, $resellerId);

        $result['notificationEmployeeByEmail'] = $this->sendEmails(
            $emails, $emailFrom, $subject, $body, $resellerId, NotificationEvents::CHANGE_RETURN_STATUS
        );

        // Шлём клиентское уведомление, только если произошла смена статуса
        if ($notificationType === self::TYPE_CHANGE && !empty($data['differences']['to'])) {
            $subject = __('complaintClientEmailSubject', $templateData, $resellerId);
            $body = __('complaintClientEmailBody', $templateData, $resellerId);
            $differencesTo = (int)$data['differences']['to'];
            $result['notificationClientByEmail'] = $this->sendEmails(
                $client->email, $emailFrom, $subject, $body, $resellerId, $client->id, NotificationEvents::CHANGE_RETURN_STATUS, $differencesTo
            );

            if (!empty($client->mobile)) {
                $res = NotificationManager::send($resellerId, $client->id, NotificationEvents::CHANGE_RETURN_STATUS, (int)$data['differences']['to'], $templateData, $error);
                if ($res) {
                    $result['notificationClientBySms']['isSent'] = true;
                }
                if (!empty($error)) {
                    $result['notificationClientBySms']['message'] = $error;
                }
            }
        }

        return $result;
    }


    protected function sendEmails(
        string|array $addresses,
        string $emailFrom,
        string $subject,
        string $message,
        int $resellerId,
        int $clientId,
        $status,
        int $differencesTo = null
    ) {
        if (
            is_array($addresses) && count($addresses) == 0 ||
            ! is_array($addresses) && empty($addresses) ||
            empty($emailFrom)
        ) {
            return false;
        }

        foreach ($addresses as $email) {
            MessagesClient::sendMessage([
                0 => [ // MessageTypes::EMAIL
                    'emailFrom' => $emailFrom,
                    'emailTo'   => $email,
                    'subject'   => $subject,
                    'message'   => $message,
                ],
            ], $resellerId, $clientId, $status, $differencesTo);
            $sended = true;
        }

        return $sended ?? false;
    }

    protected function getResellerEmailFrom()
    {
        return 'contractor@example.com';
    }

    protected function getEmailsByPermit($resellerId, $event)
    {
        // fakes the method
        return ['someemeil@example.com', 'someemeil2@example.com'];
    }
}
