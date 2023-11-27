<?php

namespace Webkul\MultiVendor\Services;

use Exception;
use Shopware\Core\Content\Mail\Service\MailService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Webkul\MultiVendor\Services\GlobalService;

class MarketplaceMailService extends GlobalService
{
    private $container;
    private $mailService;

    public function __construct(
        ContainerInterface $container,
        MailService $mailService
    )
    {
        $this->container = $container;
        $this->mailService = $mailService;
    }
    public function applySellerShipMailToAdmin($customerId, $salesChannelId)
    {
        $customer = $this->container->get('customer.repository')->search((new Criteria())->addFilter(new EqualsFilter('id', $customerId)), Context::createDefaultContext())->first();
        $customerEmail =  $customer->getEmail();
            $customerFirstName = $customer->getFirstName();
            $customerLastName = $customer->getLastName();
            $customerFullName = $customerFirstName . ' ' . $customerLastName;

        $adminUser = $this->container->get('user.repository')->search((new Criteria())->addFilter(new EqualsFilter('active', 1)),Context::createDefaultContext())->first();
        $adminEmail = $adminUser->getEmail();
        $adminFullName = $adminUser->getFirstName(). ' '. $adminUser->getLastName();
        $salesChannel = $this->container->get('sales_channel.repository')->search((new Criteria())->addFilter(new EqualsFilter('id',$salesChannelId)),Context::createDefaultContext())->getElements();
        foreach($salesChannel as $channel) {
            
            $salesChannelName = $channel->getName();
        }
        // sending mail to admin
        $adminMailTemplate = $this->container->get('marketplace_email_template.repository')->search((new Criteria())->addFilter(new EqualsFilter('technicalName', 'customer_apply_sellership')),Context::createDefaultContext())->first();
        $result = null;
        if($adminMailTemplate){
            $subject = $adminMailTemplate['subject'];
            $message = $adminMailTemplate['message'];
            $message = str_replace('{config_name}', $salesChannelName, $message);
            $message = str_replace('{seller_name}', $customerFullName, $message);
            $message = str_replace('{config_owner}', $adminFullName, $message);
            $data = new DataBag();
            $data->set(
                'recipients',
                [
                    $adminEmail => $adminFullName
                ]
            );
            $data->set('senderName', $salesChannelName);
            $data->set('salesChannelId', $salesChannelId);
            $data->set('subject', $subject);
            $data->set('contentHtml', $message);
            $data->set('contentPlain', strip_tags($message));
            try{
                $this->mailService->send($data->all(), Context::createDefaultContext());
            } catch(Exception $ex){
            
            }
            //sending mail to customer

            $customerMailTemplate = $this->container->get('marketplace_email_template.repository')->search((new Criteria())->addFilter(new EqualsFilter('technicalName', 'confirm_sellership_message')),Context::createDefaultContext())->first();
            if($customerMailTemplate) {
               
                $customerSubject = $customerMailTemplate['subject'];
                $customerMessage = $customerMailTemplate['message'];
                $customerMessage = str_replace('{config_name}', $salesChannelName, $customerMessage);
                $customerMessage = str_replace('{seller_name}', $customerFullName, $customerMessage);
                $customerdata = new DataBag();
                $customerdata->set(
                    'recipients',
                    [
                        $customerEmail => $customerFullName
                    ]
                );
                $customerdata->set('senderName', $adminFullName);
                $customerdata->set('salesChannelId', $salesChannelId);
                $customerdata->set('subject', $customerSubject);
                $customerdata->set('contentHtml', $customerMessage);
                $customerdata->set('contentPlain', strip_tags($customerMessage));
                try{
                    $result = $this->mailService->send($customerdata->all(), Context::createDefaultContext());
                } catch(Exception $ex){
                    
                }
            }
        }
        return $result;

    }
    public function customerToSeller($data,$salesChannelId) 
    {
        $customerSubject = $data['subject'];
        $customerMessage = $data['query'];
        $customerdata = new DataBag();
        $customerdata->set(
            'recipients',
            [
                $data['seller_email'] => 'Seller'
            ]
        );
        $customerdata->set('senderName', $data['user_name']);
        $customerdata->set('salesChannelId', $salesChannelId);
        
        $customerdata->set('subject', $customerSubject);
        $customerdata->set('contentHtml', $customerMessage);
        $customerdata->set('contentPlain', strip_tags($customerMessage));
       
        try{
            $this->mailService->send($customerdata->all(), Context::createDefaultContext());
        } catch(Exception $ex){
            
        }
    }
   
}