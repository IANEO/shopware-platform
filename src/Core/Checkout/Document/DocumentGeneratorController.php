<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\InvalidRequestParameterException;
use Shopware\Core\Framework\Validation\Constraint\Uuid;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @Route(defaults={"_routeScope"={"api"}})
 */
class DocumentGeneratorController extends AbstractController
{
    protected DocumentService $documentService;

    private DocumentGenerator $documentGenerator;

    private DecoderInterface $serializer;

    private DataValidator $dataValidator;

    /**
     * @internal
     */
    public function __construct(
        DocumentService $documentService,
        DocumentGenerator $documentGenerator,
        DecoderInterface $serializer,
        DataValidator $dataValidator
    ) {
        $this->documentService = $documentService;
        $this->documentGenerator = $documentGenerator;
        $this->serializer = $serializer;
        $this->dataValidator = $dataValidator;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/order/{orderId}/document/{documentTypeName}", name="api.action.document.invoice", methods={"POST"})
     *
     * @deprecated tag:v6.5.0 - will be removed, use _action/order/document/create instead
     */
    public function createDocument(Request $request, string $orderId, string $documentTypeName, Context $context): JsonResponse
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            'will be removed - use createDocuments instead'
        );

        $fileType = $request->query->getAlnum('fileType', FileTypes::PDF);
        $config = DocumentConfigurationFactory::createConfiguration($request->request->all('config'));
        $referencedDocumentId = $request->request->get('referenced_document_id');
        if ($referencedDocumentId !== null && !\is_string($referencedDocumentId)) {
            throw new InvalidRequestParameterException('referenced_document_id');
        }

        $documentIdStruct = $this->documentService->create(
            $orderId,
            $documentTypeName,
            $fileType,
            $config,
            $context,
            $referencedDocumentId,
            $request->request->getBoolean('static')
        );

        return new JsonResponse(
            [
                'documentId' => $documentIdStruct->getId(),
                'documentDeepLink' => $documentIdStruct->getDeepLinkCode(),
            ]
        );
    }

    /**
     * @Since("6.4.12.0")
     * @Route("/api/_action/order/document/{documentTypeName}/create", name="api.action.document.bulk.create", methods={"POST"}, defaults={"_acl"={"document.viewer"}})
     */
    public function createDocuments(Request $request, string $documentTypeName, Context $context): JsonResponse
    {
        $documents = $this->serializer->decode($request->getContent(), 'json');

        if (empty($documents) || !\is_array($documents)) {
            throw new InvalidRequestParameterException('Request parameters must be an array of documents object');
        }

        $operations = [];

        $definition = new DataValidationDefinition();
        $definition->addList(
            'documents',
            (new DataValidationDefinition())
                ->add('orderId', new NotBlank())
                ->add('fileType', new Choice([FileTypes::PDF]))
                ->add('config', new Type('array'))
                ->add('static', new Type('bool'))
                ->add('referencedDocumentId', new Uuid())
        );

        $this->dataValidator->validate($documents, $definition);

        foreach ($documents as $operation) {
            $operations[$operation['orderId']] = new DocumentGenerateOperation(
                $operation['orderId'],
                $operation['fileType'] ?? FileTypes::PDF,
                $operation['config'] ?? [],
                $operation['referencedDocumentId'] ?? null,
                $operation['static'] ?? false
            );
        }

        return new JsonResponse($this->documentGenerator->generate($documentTypeName, $operations, $context));
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/document/{documentId}/upload", name="api.action.document.upload", methods={"POST"})
     */
    public function uploadToDocument(Request $request, string $documentId, Context $context): JsonResponse
    {
        $documentIdStruct = $this->documentGenerator->upload(
            $documentId,
            $context,
            $request
        );

        return new JsonResponse(
            [
                'documentId' => $documentIdStruct->getId(),
                'documentMediaId' => $documentIdStruct->getMediaId(),
                'documentDeepLink' => $documentIdStruct->getDeepLinkCode(),
            ]
        );
    }
}
