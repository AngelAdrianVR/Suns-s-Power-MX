<script setup>
import { h } from 'vue';
import { NTag, NIcon, NDataTable, NEmpty } from 'naive-ui';
import { MailOutline, CallOutline, Star } from '@vicons/ionicons5';

const props = defineProps({
    client: Object
});

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
    { title: 'Notas', key: 'notes', ellipsis: { tooltip: true } }
];
</script>

<template>
    <div>
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3">
            <div>
                <h3 class="text-base sm:text-lg font-bold text-gray-800">Directorio</h3>
                <p class="text-xs sm:text-sm text-gray-500">Personas de contacto registradas</p>
            </div>
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
    </div>
</template>