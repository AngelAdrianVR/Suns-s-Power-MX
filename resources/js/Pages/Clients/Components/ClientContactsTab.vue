<script setup>
import { ref, h } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import {
    NTag, NIcon, NDataTable, NEmpty, NButton, NModal, NCard,
    NForm, NFormItem, NInput, NCheckbox, NSpace, createDiscreteApi
} from 'naive-ui';
import {
    MailOutline, CallOutline, Star, AddOutline, CreateOutline,
    TrashOutline, PeopleOutline
} from '@vicons/ionicons5';

const props = defineProps({
    client: Object
});

const { notification, dialog } = createDiscreteApi(['notification', 'dialog']);

// --- MODAL AGREGAR/EDITAR ---
const showContactModal = ref(false);
const editingContact = ref(null); // null = crear, object = editar
const contactForm = useForm({
    contactable_id: props.client.id,
    contactable_type: 'App\\Models\\Client',
    name: '',
    job_title: '',
    email: '',
    phone: '',
    is_primary: false,
    notes: '',
});

const openAddModal = () => {
    editingContact.value = null;
    contactForm.reset();
    contactForm.contactable_id = props.client.id;
    contactForm.contactable_type = 'App\\Models\\Client';
    showContactModal.value = true;
};

const openEditModal = (contact) => {
    editingContact.value = contact;
    contactForm.contactable_id = null;
    contactForm.contactable_type = null;
    contactForm.name = contact.name;
    contactForm.job_title = contact.job_title || '';
    contactForm.email = contact.email || '';
    contactForm.phone = contact.phone || '';
    contactForm.is_primary = !!contact.is_primary;
    contactForm.notes = contact.notes || '';
    showContactModal.value = true;
};

const submitContact = () => {
    if (!contactForm.name.trim()) {
        notification.warning({ title: 'Atención', content: 'El nombre del contacto es obligatorio.', duration: 3000 });
        return;
    }

    if (editingContact.value) {
        contactForm.patch(route('contacts.update', editingContact.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showContactModal.value = false;
                notification.success({ title: 'Actualizado', content: 'Contacto actualizado correctamente.', duration: 3000 });
            },
        });
    } else {
        contactForm.post(route('contacts.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showContactModal.value = false;
                notification.success({ title: 'Agregado', content: 'Contacto agregado correctamente.', duration: 3000 });
            },
        });
    }
};

// --- ELIMINAR ---
const confirmDelete = (contact) => {
    if (props.client.contacts?.length <= 1) {
        notification.warning({ title: 'No permitido', content: 'Debe existir al menos un contacto.', duration: 3000 });
        return;
    }
    dialog.warning({
        title: 'Eliminar Contacto',
        content: `¿Estás seguro de eliminar a "${contact.name}"? Esta acción no se puede deshacer.`,
        positiveText: 'Sí, Eliminar',
        negativeText: 'Cancelar',
        onPositiveClick: () => {
            router.delete(route('contacts.destroy', contact.id), {
                preserveScroll: true,
                onSuccess: () => notification.success({ title: 'Eliminado', content: 'Contacto eliminado.', duration: 3000 }),
            });
        }
    });
};

// --- COLUMNAS ---
const contactColumns = [
    {
        title: '',
        key: 'is_primary',
        width: 40,
        render(row) {
            return row.is_primary
                ? h(NIcon, { color: '#f59e0b', size: 18 }, { default: () => h(Star) })
                : null;
        }
    },
    {
        title: 'Nombre',
        key: 'name',
        render: (row) => h('div', { class: 'font-medium text-gray-800' }, row.name)
    },
    {
        title: 'Puesto / Parentesco',
        key: 'job_title',
        render: (row) => row.job_title ? h(NTag, { size: 'small', bordered: false, type: 'default' }, { default: () => row.job_title }) : '-'
    },
    {
        title: 'Email',
        key: 'email',
        render(row) {
            if (!row.email) return '-';
            return h('a', { href: `mailto:${row.email}`, class: 'text-indigo-600 hover:underline flex items-center gap-1' }, [
                h(NIcon, null, { default: () => h(MailOutline) }), row.email
            ]);
        }
    },
    {
        title: 'Teléfono',
        key: 'phone',
        render(row) {
            if (!row.phone) return '-';
            return h('a', { href: `tel:${row.phone}`, class: 'text-green-600 hover:underline flex items-center gap-1' }, [
                h(NIcon, null, { default: () => h(CallOutline) }), row.phone
            ]);
        }
    },
    { title: 'Notas', key: 'notes', ellipsis: { tooltip: true } },
    {
        title: '',
        key: 'actions',
        width: 100,
        render(row) {
            return h(NSpace, { justify: 'end' }, () => [
                h(NButton, {
                    circle: true, size: 'small', quaternary: true, type: 'warning',
                    onClick: () => openEditModal(row)
                }, { icon: () => h(NIcon, null, { default: () => h(CreateOutline) }) }),
                h(NButton, {
                    circle: true, size: 'small', quaternary: true, type: 'error',
                    onClick: () => confirmDelete(row)
                }, { icon: () => h(NIcon, null, { default: () => h(TrashOutline) }) }),
            ]);
        }
    },
];
</script>

<template>
    <div>
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3">
            <div>
                <h3 class="text-base sm:text-lg font-bold text-gray-800">Directorio</h3>
                <p class="text-xs sm:text-sm text-gray-500">Personas de contacto registradas</p>
            </div>
            <n-button size="small" type="primary" round @click="openAddModal">
                <template #icon><n-icon><AddOutline /></n-icon></template>
                Agregar Contacto
            </n-button>
        </div>

        <div class="-mx-4 px-4 sm:mx-0 sm:px-0 overflow-x-auto">
            <div class="min-w-[600px] sm:min-w-full">
                <n-data-table
                    :columns="contactColumns"
                    :data="client.contacts"
                    :bordered="false"
                    size="medium"
                    class="mb-2"
                />
                <n-empty v-if="!client.contacts || client.contacts.length === 0" description="No hay contactos registrados" class="py-8"/>
            </div>
        </div>

        <!-- Modal Agregar / Editar Contacto -->
        <n-modal v-model:show="showContactModal" :mask-closable="false">
            <n-card
                style="width: 480px"
                :title="editingContact ? 'Editar Contacto' : 'Nuevo Contacto'"
                :bordered="false"
                size="huge"
                role="dialog"
                aria-modal="true"
            >
                <template #header-extra>
                    <n-icon size="24" :component="PeopleOutline" class="text-indigo-500" />
                </template>

                <n-form :model="contactForm">
                    <n-form-item label="Nombre" path="name" required>
                        <n-input v-model:value="contactForm.name" placeholder="Nombre completo" />
                    </n-form-item>
                    <n-form-item label="Puesto / Parentesco" path="job_title">
                        <n-input v-model:value="contactForm.job_title" placeholder="Ej: Gerente, Esposo(a)..." />
                    </n-form-item>
                    <n-form-item label="Email" path="email">
                        <n-input v-model:value="contactForm.email" placeholder="correo@ejemplo.com" />
                    </n-form-item>
                    <n-form-item label="Teléfono" path="phone">
                        <n-input v-model:value="contactForm.phone" placeholder="55 1234 5678" />
                    </n-form-item>
                    <n-form-item label="Notas" path="notes">
                        <n-input v-model:value="contactForm.notes" type="textarea" placeholder="Notas adicionales..." :autosize="{ minRows: 2 }" />
                    </n-form-item>
                    <n-form-item label="Contacto Principal" path="is_primary">
                        <n-checkbox v-model:checked="contactForm.is_primary">
                            Marcar como contacto principal
                        </n-checkbox>
                    </n-form-item>
                </n-form>

                <template #footer>
                    <div class="flex justify-end gap-3">
                        <n-button @click="showContactModal = false" :disabled="contactForm.processing">Cancelar</n-button>
                        <n-button type="primary" @click="submitContact" :loading="contactForm.processing">
                            {{ editingContact ? 'Guardar Cambios' : 'Agregar Contacto' }}
                        </n-button>
                    </div>
                </template>
            </n-card>
        </n-modal>
    </div>
</template>