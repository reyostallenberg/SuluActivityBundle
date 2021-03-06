<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ActivityBundle\Controller;

use FOS\RestBundle\Routing\ClassResourceInterface;
use Hateoas\Representation\CollectionRepresentation;
use JMS\Serializer\SerializationContext;
use Sulu\Bundle\ActivityBundle\Api\Activity;
use Sulu\Bundle\ActivityBundle\Entity\Activity as ActivityEntity;
use Sulu\Bundle\ActivityBundle\Entity\ActivityPriority;
use Sulu\Bundle\ActivityBundle\Entity\ActivityStatus;
use Sulu\Bundle\ActivityBundle\Entity\ActivityType;
use Sulu\Bundle\ContactBundle\Entity\AccountInterface;
use Sulu\Component\Contact\Model\ContactInterface;
use Sulu\Component\Contact\Model\ContactRepositoryInterface;
use Sulu\Component\Rest\Exception\EntityNotFoundException;
use Sulu\Component\Rest\Exception\RestException;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactory;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineConcatenationFieldDescriptor;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptor;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineJoinDescriptor;
use Sulu\Component\Rest\ListBuilder\ListRepresentation;
use Sulu\Component\Rest\RestController;
use Sulu\Component\Rest\RestHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes activities available through a REST API
 */
class ActivityController extends RestController implements ClassResourceInterface
{
    /**
     * {@inheritdoc}
     */
    protected static $entityName = 'SuluActivityBundle:Activity';
    protected static $activityStatusEntityName = 'SuluActivityBundle:ActivityStatus';
    protected static $activityTypeEntityName = 'SuluActivityBundle:ActivityType';
    protected static $activityPriorityEntityName = 'SuluActivityBundle:ActivityPriority';
    protected $accountEntityName;

    /**
     * @var string
     */
    protected $basePath = 'admin/api/activities';
    protected $bundlePrefix = 'contact.activities.';
    protected static $entityKey = 'activities';

    /**
     * TODO: move the field descriptors to a manager
     *
     * @var DoctrineFieldDescriptor[]
     */
    protected $fieldDescriptors;

    protected $joinDescriptors;

    /**
     * TODO: move field descriptors to a manager
     */
    public function setContainer(ContainerInterface $container = null)
    {
        parent::setContainer($container);

        $this->accountEntityName = $this->container->getParameter('sulu_contact.account.entity');

        $this->fieldDescriptors = [];
        $this->joinDescriptors = [];
        $this->fieldDescriptors['id'] = new DoctrineFieldDescriptor(
            'id',
            'id',
            self::$entityName,
            'public.id',
            [],
            true,
            false,
            '',
            '',
            '',
            false
        );
        $this->fieldDescriptors['subject'] = new DoctrineFieldDescriptor(
            'subject',
            'subject',
            self::$entityName,
            'contact.activities.subject',
            [],
            false,
            true,
            '',
            '180px',
            '',
            true
        );
        $this->fieldDescriptors['note'] = new DoctrineFieldDescriptor(
            'note',
            'note',
            self::$entityName,
            'contact.activities.note',
            [],
            true,
            false,
            '',
            '',
            '',
            false
        );
        $this->fieldDescriptors['dueDate'] = new DoctrineFieldDescriptor(
            'dueDate',
            'dueDate',
            self::$entityName,
            'contact.activities.dueDate',
            [],
            false,
            true,
            'date',
            '',
            '',
            true
        );
        $this->fieldDescriptors['startDate'] = new DoctrineFieldDescriptor(
            'startDate',
            'startDate',
            self::$entityName,
            'contact.activities.startDate',
            [],
            true,
            false,
            'date',
            '',
            '',
            false
        );
        $this->fieldDescriptors['created'] = new DoctrineFieldDescriptor(
            'created',
            'created',
            self::$entityName,
            'public.created',
            [],
            true,
            false,
            'date',
            '',
            '',
            false
        );
        $this->fieldDescriptors['changed'] = new DoctrineFieldDescriptor(
            'changed',
            'changed',
            self::$entityName,
            'public.changed',
            [],
            true,
            false,
            'date',
            '',
            '',
            false
        );
        $this->fieldDescriptors['activityStatus'] = new DoctrineFieldDescriptor(
            'name',
            'activityStatus',
            self::$activityStatusEntityName,
            'contact.activities.status',
            [
                self::$activityStatusEntityName => new DoctrineJoinDescriptor(
                    self::$activityStatusEntityName,
                    self::$entityName . '.activityStatus'
                ),
            ],
            false,
            true,
            'translation',
            '',
            '',
            true
        );
        $this->fieldDescriptors['activityPriority'] = new DoctrineFieldDescriptor(
            'name',
            'activityPriority',
            self::$activityPriorityEntityName,
            'contact.activities.priority',
            [
                self::$activityPriorityEntityName => new DoctrineJoinDescriptor(
                    self::$activityPriorityEntityName,
                    self::$entityName . '.activityPriority'
                ),
            ],
            false,
            true,
            'translation',
            '',
            '',
            true
        );
        $this->fieldDescriptors['activityType'] = new DoctrineFieldDescriptor(
            'name',
            'activityType',
            self::$activityTypeEntityName,
            'contact.activities.type',
            [
                self::$activityTypeEntityName => new DoctrineJoinDescriptor(
                    self::$activityTypeEntityName,
                    self::$entityName . '.activityType'
                ),
            ],
            true,
            false,
            'translation',
            '',
            '',
            true
        );
        $this->fieldDescriptors['assignedContact'] =
            new DoctrineConcatenationFieldDescriptor(
                [
                    new DoctrineFieldDescriptor(
                        'firstName',
                        'firstName',
                        $this->getContactEntityName(),
                        'public.firstName',
                        [
                            $this->getContactEntityName() =>
                                new DoctrineJoinDescriptor(
                                    $this->getContactEntityName(),
                                    self::$entityName . '.assignedContact'
                                ),
                        ]
                    ),
                    new DoctrineFieldDescriptor(
                        'lastName',
                        'lastName',
                        $this->getContactEntityName(),
                        'public.lastName',
                        [
                            $this->getContactEntityName() =>
                                new DoctrineJoinDescriptor(
                                    $this->getContactEntityName(),
                                    self::$entityName . '.assignedContact'
                                ),
                        ]
                    ),
                ],
                'assignedContact',
                'contact.activities.assignedContact',
                ' ',
                false,
                false,
                '',
                '',
                '',
                false
            );
        $this->joinDescriptors['account'] = new DoctrineFieldDescriptor(
            'id',
            'account',
            $this->accountEntityName,
            '',
            [
                $this->accountEntityName => new DoctrineJoinDescriptor(
                    $this->accountEntityName,
                    self::$entityName . '.account'
                ),
            ],
            '',
            false
        );
        $this->joinDescriptors['contact'] = new DoctrineFieldDescriptor(
            'id',
            'contact',
            $this->getContactEntityName() . 'contact',
            '',
            [
                $this->getContactEntityName() . 'contact' => new DoctrineJoinDescriptor(
                    $this->getContactEntityName() . 'contact',
                    self::$entityName . '.contact'
                ),
            ],
            false
        );
    }

    /**
     * Returns all fields that can be used by list.
     *
     * @return Response
     */
    public function fieldsAction()
    {
        return $this->handleView(
            $this->view(array_values($this->fieldDescriptors), 200)
        );
    }

    /**
     * Shows a single activity with the given id.
     *
     * @param int $id
     *
     * @return Response
     */
    public function getAction($id)
    {
        $view = $this->responseGetById(
            $id,
            function ($id) {
                return $this->getDoctrine()
                    ->getRepository(self::$entityName)
                    ->findActivitiesById($id);
            }
        );

        return $this->handleView($view);
    }

    /**
     * Lists all activities.
     *
     * optional parameter 'flat' calls listAction
     * optional parameter 'contact' calls listAction for all activities for a
     *  contact (in combination with flat)
     * optional parameter 'account' calls listAction for all activities for a
     *  account (in combination with flat)
     *
     * @param Request $request
     *
     * @return Response
     */
    public function cgetAction(Request $request)
    {
        $filter = array();

        $type = $request->get('type');
        if ($type) {
            $filter['type'] = $type;
        }

        $account = $request->get('account');
        if ($account) {
            $filter['account'] = $account;
        }

        $contact = $request->get('contact');
        if ($contact) {
            $filter['contact'] = $contact;
        }

        if ($request->get('flat') == 'true') {
            /** @var RestHelperInterface $restHelper */
            $restHelper = $this->get('sulu_core.doctrine_rest_helper');

            /** @var DoctrineListBuilderFactory $factory */
            $factory = $this->get('sulu_core.doctrine_list_builder_factory');

            $listBuilder = $factory->create(self::$entityName);

            $restHelper->initializeListBuilder(
                $listBuilder,
                $this->fieldDescriptors
            );

            foreach ($filter as $key => $value) {
                $listBuilder->where($this->joinDescriptors[$key], $value);
            }

            $list = new ListRepresentation(
                $listBuilder->execute(),
                self::$entityKey,
                'get_activities',
                $request->query->all(),
                $listBuilder->getCurrentPage(),
                $listBuilder->getLimit(),
                $listBuilder->count()
            );
        } else {
            $activities = $this->getDoctrine()->getRepository(
                self::$entityName
            )->findAllActivities();
            $list = new CollectionRepresentation($activities, self::$entityKey);
        }

        $view = $this->view($list, 200);

        return $this->handleView($view);
    }

    /**
     * Updates an activity with a given id.
     *
     * @param int $id
     * @param Request $request
     *
     * @return Response
     */
    public function putAction($id, Request $request)
    {
        try {
            $em = $this->getDoctrine()->getManager();
            $activity = $this->getEntityById(self::$entityName, $id);

            $this->processActivityData($activity, $request);

            $em->persist($activity);
            $em->flush();

            $view = $this->view(
                new Activity(
                    $activity,
                    $this->getUser()->getLocale()
                ),
                200
            );

            $view->setSerializationContext(
                SerializationContext::create()->setGroups(array('partialAccount', 'partialContact', 'fullActivity'))
            );
        } catch (EntityNotFoundException $enfe) {
            $view = $this->view($enfe->toArray(), 404);
        } catch (RestException $re) {
            $view = $this->view($re->toArray(), 400);
        }

        return $this->handleView($view);
    }

    /**
     * Deletes an activity with a given id.
     *
     * @param int $id
     *
     * @return Response
     */
    public function deleteAction($id)
    {
        $delete = function ($id) {
            $em = $this->getDoctrine()->getManager();
            $activity = $this->getEntityById(self::$entityName, $id);
            $em->remove($activity);
            $em->flush();
        };

        $view = $this->responseDelete($id, $delete);

        return $this->handleView($view);
    }

    /**
     * Creates a new activity.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function postAction(Request $request)
    {
        try {
            $em = $this->getDoctrine()->getManager();
            $activity = new ActivityEntity();

            $this->processActivityData($activity, $request);

            $activity->setCreator($this->getUser());
            $activity->setCreated(new \DateTime());

            $em->persist($activity);
            $em->flush();

            $view = $this->view(
                new Activity(
                    $activity,
                    $this->getUser()->getLocale()
                ),
                200
            );

            $view->setSerializationContext(
                SerializationContext::create()->setGroups(array('partialAccount', 'partialContact', 'fullActivity'))
            );
        } catch (EntityNotFoundException $enfe) {
            $view = $this->view($enfe->toArray(), 404);
        } catch (RestException $re) {
            $view = $this->view($re->toArray(), 400);
        }

        return $this->handleView($view);
    }

    /**
     * Processes the data for an activity from an request.
     *
     * @param ActivityEntity $activity
     * @param Request $request
     *
     * @throws RestException
     */
    protected function processActivityData(ActivityEntity $activity, Request $request)
    {
        $this->processRequiredData($activity, $request);

        $note = $request->get('note');
        $status = $request->get('activityStatus');
        $priority = $request->get('activityPriority');
        $type = $request->get('activityType');
        $startDate = $request->get('startDate');
        $belongsToAccount = $request->get('account');
        $belongsToContact = $request->get('contact');

        if (!is_null($note)) {
            $activity->setNote($note);
        }
        if (!is_null($status)) {
            /* @var ActivityStatus $activityStatus */
            $activityStatus = $this->getEntityById(
                self::$activityStatusEntityName,
                $status['id']
            );
            $activity->setActivityStatus($activityStatus);
        }
        if (!is_null($priority)) {
            /* @var ActivityPriority $activityPriority */
            $activityPriority = $this->getEntityById(
                self::$activityPriorityEntityName,
                $priority['id']
            );
            $activity->setActivityPriority($activityPriority);
        }
        if (!is_null($type)) {
            /* @var ActivityType $activityType */
            $activityType = $this->getEntityById(
                self::$activityTypeEntityName,
                $type['id']
            );
            $activity->setActivityType($activityType);
        }
        if (!is_null($startDate)) {
            $activity->setStartDate(new \DateTime($startDate));
        }
        if (!is_null($belongsToAccount)) {
            /* @var AccountInterface $account */
            $account = $this->getEntityById(
                $this->accountEntityName,
                $belongsToAccount['id']
            );
            $activity->setAccount($account);
            $activity->setContact(null);
        } else {
            if (is_null($belongsToContact)) {
                throw new RestException(
                    'No account or contact set!'
                );
            }
            /* @var ContactInterface $contact */
            $contact = $this->retrieveContactById($belongsToContact['id']);
            $activity->setContact($contact);
            $activity->setAccount(null);
        }
    }

    /**
     * Returns an entity for a specific id.
     *
     * @param string $entityName
     * @param int $id
     *
     * @throws EntityNotFoundException
     *
     * @return mixed
     */
    protected function getEntityById($entityName, $id)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository($entityName)->find($id);
        if (!$entity) {
            throw new EntityNotFoundException($entityName, $id);
        }

        return $entity;
    }

    /**
     * Sets required data for an activity.
     *
     * @param ActivityEntity $activity
     * @param Request $request
     *
     * @throws RestException
     */
    private function processRequiredData(ActivityEntity $activity, Request $request)
    {
        $subject = $request->get('subject');
        $dueDate = $request->get('dueDate');
        $assignedContactData = $request->get('assignedContact');

        if ($subject == null ||
            $dueDate == null ||
            $assignedContactData == null
        ) {
            throw new RestException(
                'There is no name or dueDate or assignedContact for the activity given'
            );
        }

        // required data
        $activity->setSubject($subject);
        $activity->setDueDate(new \DateTime($dueDate));

        if (!is_null($assignedContactData['id'])) {
            $assignedContact = $this->retrieveContactById($assignedContactData['id']);
            $activity->setAssignedContact($assignedContact);
        }

        // changer and changed
        $activity->setChanged(new \DateTime());
        $activity->setChanger($this->getUser());
    }

    /**
     * Returns contact by id and throws exception if not found.
     *
     * @param int $id
     *
     * @throws EntityNotFoundException
     *
     * @return ContactInterface
     */
    private function retrieveContactById($id)
    {
        $contact = $this->getContactRepository()->find($id);
        if (!$contact) {
            throw new EntityNotFoundException($this->getContactEntityName(), $id);
        }

        return $contact;
    }

    /**
     * @return string
     */
    private function getContactEntityName()
    {
        return $this->getContactRepository()->getClassName();
    }

    /**
     * @return ContactRepositoryInterface
     */
    private function getContactRepository()
    {
        return $this->get('sulu.repository.contact');
    }
}
