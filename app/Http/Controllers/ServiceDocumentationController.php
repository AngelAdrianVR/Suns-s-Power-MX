<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Product;
use App\Models\ServiceDocumentationAttachment;
use App\Models\ServiceDocumentationStep;
use App\Models\ServiceOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ServiceDocumentationController extends Controller
{
    private function branchId()
    {
        return session('current_branch_id') ?? Auth::user()->branch_id;
    }

    private function authorizeOrder(ServiceOrder $serviceOrder)
    {
        if ($serviceOrder->branch_id !== $this->branchId()) {
            abort(403);
        }
    }

    // =====================================================
    // CONFIGURACIÓN DE PASOS / DOCUMENTOS (sidenav)
    // =====================================================

    public function index()
    {
        $steps = ServiceDocumentationStep::query()
            ->where('branch_id', $this->branchId())
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Setting/ServiceDocumentation/Index', [
            'steps' => $steps,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
        ]);

        $maxOrder = ServiceDocumentationStep::where('branch_id', $this->branchId())->max('order');

        ServiceDocumentationStep::create([
            'branch_id' => $this->branchId(),
            'title' => $request->title,
            'description' => $request->description,
            'order' => ($maxOrder ?? 0) + 1,
            'is_active' => true,
        ]);

        return back()->with('success', 'Documento agregado a la documentación de servicio.');
    }

    public function update(Request $request, ServiceDocumentationStep $step)
    {
        if ($step->branch_id !== $this->branchId()) {
            abort(403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'is_active' => 'sometimes|boolean',
        ]);

        $step->update([
            'title' => $request->title,
            'description' => $request->description,
            'is_active' => $request->boolean('is_active', $step->is_active),
        ]);

        return back()->with('success', 'Documento actualizado.');
    }

    public function destroy(ServiceDocumentationStep $step)
    {
        if ($step->branch_id !== $this->branchId()) {
            abort(403);
        }

        // Al eliminar el documento configurado, también se limpian sus archivos recopilados
        $step->attachments()->delete();
        $step->delete();

        return back()->with('success', 'Documento eliminado de la configuración.');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer',
            'items.*.order' => 'required|integer',
        ]);

        foreach ($request->items as $item) {
            ServiceDocumentationStep::where('id', $item['id'])
                ->where('branch_id', $this->branchId())
                ->update(['order' => $item['order']]);
        }

        return back()->with('success', 'Orden guardado.');
    }

    /**
     * Carga los 5 documentos predeterminados sugeridos para la sucursal actual
     * (solo si aún no hay ninguno configurado).
     */
    public function defaults()
    {
        $count = ServiceDocumentationStep::where('branch_id', $this->branchId())->count();

        if ($count > 0) {
            return back()->with('error', 'Ya existen documentos configurados para esta sucursal.');
        }

        $defaults = [
            [
                'title' => 'Identificación oficial (INE)',
                'description' => 'Adjunta o vincula la identificación oficial vigente del cliente desde su expediente.',
            ],
            [
                'title' => 'Situación fiscal (RFC / Constancia)',
                'description' => 'Adjunta o vincula la constancia de situación fiscal o RFC del cliente.',
            ],
            [
                'title' => 'Comprobante de domicilio',
                'description' => 'Adjunta o vincula el comprobante del domicilio donde se realiza la instalación.',
            ],
            [
                'title' => 'Manuales y fichas técnicas de equipos',
                'description' => 'Manuales de los dispositivos del sistema. Ya están adjuntos en cada producto; si es necesario, adjúntalos o vincúlalos aquí.',
            ],
            [
                'title' => 'Evidencias de instalación',
                'description' => 'Fotografías del avance y término de la instalación, vinculadas a la orden de servicio.',
            ],
        ];

        foreach ($defaults as $index => $default) {
            ServiceDocumentationStep::create([
                'branch_id' => $this->branchId(),
                'title' => $default['title'],
                'description' => $default['description'],
                'order' => $index + 1,
                'is_active' => true,
            ]);
        }

        return back()->with('success', 'Se cargaron los documentos predeterminados.');
    }

    // =====================================================
    // ASISTENTE DE DOCUMENTACIÓN (desde la orden de servicio)
    // =====================================================

    /**
     * Datos del asistente: pasos activos, documentos del cliente,
     * archivos de la orden y lo ya recopilado.
     */
    public function wizard(ServiceOrder $serviceOrder)
    {
        $this->authorizeOrder($serviceOrder);

        $steps = ServiceDocumentationStep::query()
            ->where('branch_id', $serviceOrder->branch_id)
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        $clientDocuments = collect();
        if ($serviceOrder->client) {
            $clientDocuments = $serviceOrder->client->getMedia('documents')
                ->map(fn ($media) => $this->mediaPayload($media));
        }

        // Archivos adjuntos directos a la orden (colección general, sin los ya subidos al expediente)
        $directOrderMedia = $serviceOrder->media()
            ->where('collection_name', '!=', 'service_documentation')
            ->get()
            ->map(fn ($media) => $this->mediaPayload($media));

        // Evidencias del checklist (subidas a cada evidencia requerida)
        $evidenceMedia = $serviceOrder->evidences()
            ->with('media')
            ->get()
            ->flatMap(fn ($evidence) => $evidence->media->map(fn ($media) => $this->mediaPayload($media)));

        $orderMedia = $directOrderMedia->merge($evidenceMedia);

        // Productos de la orden que tienen al menos un archivo vinculado (manuales, fichas, etc.)
        $products = $serviceOrder->items()
            ->with(['product.media'])
            ->get()
            ->map(function ($item) {
                $product = $item->product;
                if (!$product) {
                    return null;
                }

                // Solo documentos del producto (manuales, fichas, certificados), no la foto
                $media = $product->getMedia('product_attachments')
                    ->map(fn ($media) => $this->mediaPayload($media))
                    ->values();

                if ($media->isEmpty()) {
                    return null;
                }

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => (float) $item->quantity,
                    'media' => $media,
                ];
            })
            ->filter()
            ->values();

        $attachments = ServiceDocumentationAttachment::where('service_order_id', $serviceOrder->id)
            ->orderBy('id')
            ->get()
            ->map(fn ($attachment) => $this->attachmentPayload($attachment))
            ->groupBy('step_id');

        return response()->json([
            'steps' => $steps,
            'client_documents' => $clientDocuments,
            'order_media' => $orderMedia,
            'products' => $products,
            'attachments' => $attachments,
        ]);
    }

    /**
     * Sube archivos nuevos y los asigna al paso indicado.
     */
    public function upload(Request $request, ServiceOrder $serviceOrder)
    {
        $this->authorizeOrder($serviceOrder);

        $request->validate([
            'step_id' => 'required|integer',
            'files' => 'required|array|max:10',
            'files.*' => 'file|max:20480',
        ]);

        $stepExists = ServiceDocumentationStep::where('id', $request->step_id)
            ->where('branch_id', $serviceOrder->branch_id)
            ->exists();

        abort_if(!$stepExists, 422, 'El documento seleccionado no existe.');

        $created = [];

        foreach ($request->file('files') as $file) {
            $media = $serviceOrder->addMedia($file)->toMediaCollection('service_documentation');

            $attachment = ServiceDocumentationAttachment::create([
                'service_order_id' => $serviceOrder->id,
                'step_id' => $request->step_id,
                'source' => ServiceDocumentationAttachment::SOURCE_UPLOAD,
                'media_id' => $media->id,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'file_path' => $media->getPath(),
                'url' => $media->getUrl(),
                'created_by' => Auth::id(),
            ]);

            $created[] = $this->attachmentPayload($attachment);
        }

        return response()->json(['attachments' => $created]);
    }

    /**
     * Vincula archivos existentes (del cliente o de la orden) a un paso.
     */
    public function link(Request $request, ServiceOrder $serviceOrder)
    {
        $this->authorizeOrder($serviceOrder);

        $request->validate([
            'step_id' => 'required|integer',
            'source' => 'required|in:client,order,product',
            'media_ids' => 'required|array|min:1',
            'media_ids.*' => 'integer',
        ]);

        $stepExists = ServiceDocumentationStep::where('id', $request->step_id)
            ->where('branch_id', $serviceOrder->branch_id)
            ->exists();

        abort_if(!$stepExists, 422, 'El documento seleccionado no existe.');

        $created = [];

        foreach ($request->media_ids as $mediaId) {
            $media = Media::find($mediaId);
            if (!$media) {
                continue;
            }

            // Seguridad: el archivo debe pertenecer al cliente, a la orden o a un producto de la orden según la fuente
            if ($request->source === ServiceDocumentationAttachment::SOURCE_CLIENT) {
                $allowed = $media->model_type === Client::class && $media->model_id === $serviceOrder->client_id;
            } elseif ($request->source === ServiceDocumentationAttachment::SOURCE_PRODUCT) {
                $allowedProductIds = $serviceOrder->items()
                    ->pluck('product_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $allowed = $media->model_type === Product::class && in_array((int) $media->model_id, $allowedProductIds, true);
            } else {
                $allowed = $media->model_type === ServiceOrder::class && $media->model_id === $serviceOrder->id;
            }

            if (!$allowed) {
                continue;
            }

            $attachment = ServiceDocumentationAttachment::updateOrCreate([
                'service_order_id' => $serviceOrder->id,
                'step_id' => $request->step_id,
                'source' => $request->source,
                'media_id' => $media->id,
            ], [
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'file_path' => $media->getPath(),
                'url' => $media->getUrl(),
                'created_by' => Auth::id(),
            ]);

            $created[] = $this->attachmentPayload($attachment);
        }

        return response()->json(['attachments' => $created]);
    }

    /**
     * Quita un archivo de un paso (si fue subido en el asistente, también borra el archivo físico).
     */
    public function removeAttachment(ServiceOrder $serviceOrder, ServiceDocumentationAttachment $attachment)
    {
        abort_if($attachment->service_order_id !== $serviceOrder->id, 404);

        if ($attachment->source === ServiceDocumentationAttachment::SOURCE_UPLOAD && $attachment->media_id) {
            Media::where('id', $attachment->media_id)->delete();
        }

        $attachment->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Vista imprimible (se abre en pestaña nueva con botón Imprimir / Guardar como PDF).
     * Si llega ?step={id}, se imprime solo ese documento.
     */
    public function printDocumentation(Request $request, ServiceOrder $serviceOrder)
    {
        $this->authorizeOrder($serviceOrder);

        $stepId = $request->query('step');

        $serviceOrder->load(['client.contacts', 'technician']);

        $stepsQuery = ServiceDocumentationStep::query()
            ->where('branch_id', $serviceOrder->branch_id)
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('id');

        if ($stepId) {
            $stepsQuery->where('id', $stepId);
        }

        $steps = $stepsQuery->get();

        $attachments = ServiceDocumentationAttachment::where('service_order_id', $serviceOrder->id)
            ->when($stepId, fn ($query) => $query->where('step_id', $stepId))
            ->orderBy('step_id')
            ->orderBy('id')
            ->get()
            ->groupBy('step_id');

        $primaryContact = $serviceOrder->client?->contacts->first();

        $orderPayload = [
            'id' => $serviceOrder->id,
            'service_number' => $serviceOrder->service_number,
            'status' => $serviceOrder->status,
            'created_at' => $serviceOrder->created_at?->format('d/m/Y'),
            'start_date' => $serviceOrder->start_date?->format('d/m/Y H:i'),
            'completion_date' => $serviceOrder->completion_date?->format('d/m/Y H:i'),
            'full_installation_address' => $serviceOrder->full_installation_address,
            'client' => $serviceOrder->client ? [
                'name' => $serviceOrder->client->name,
                'tax_id' => $serviceOrder->client->tax_id,
                'contact_name' => $primaryContact?->name,
                'contact_phone' => $primaryContact?->phone,
            ] : null,
            'technician' => $serviceOrder->technician?->name,
        ];

        return Inertia::render('ServiceOrders/DocumentationPrint', [
            'order' => $orderPayload,
            'steps' => $steps->map(function ($step) use ($attachments) {
                $stepAttachments = $attachments[$step->id] ?? collect();

                return [
                    'id' => $step->id,
                    'title' => $step->title,
                    'description' => $step->description,
                    'attachments' => $stepAttachments->map(fn ($attachment) => $this->attachmentPayload($attachment))->values(),
                ];
            })->values(),
            'single' => (bool) $stepId,
            'generated_at' => now()->format('d/m/Y H:i'),
            'generated_by' => Auth::user()->name,
        ]);
    }

    // =====================================================
    // Helpers
    // =====================================================

    private function mediaPayload(Media $media): array
    {
        return [
            'id' => $media->id,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->human_readable_size,
            'url' => $media->getUrl(),
            'collection' => $media->collection_name,
            'category' => $media->getCustomProperty('category'),
        ];
    }

    private function attachmentPayload(ServiceDocumentationAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'step_id' => (int) $attachment->step_id,
            'source' => $attachment->source,
            'file_name' => $attachment->file_name,
            'mime_type' => $attachment->mime_type,
            'url' => $attachment->url,
        ];
    }
}
