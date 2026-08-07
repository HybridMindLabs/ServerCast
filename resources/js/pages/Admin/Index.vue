<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { Radio, Send, CheckCircle2, Clock, XCircle, WifiOff, Trash2, History } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import GameIcon from '@/components/UI/GameIcon.vue';
import { computed } from 'vue';
import { useLocale } from '@/composables/useLocale';

interface ServerOption {
    id: number;
    name: string;
    address: string;
    game: string | null;
    game_slug: string | null;
    bridge_enabled: boolean;
    online: boolean;
}

interface Delivery { server_id: number; status: string }
interface Broadcast {
    id: number;
    message: string;
    target_type: string;
    sent_by: string | null;
    created_at: string | null;
    deliveries: Delivery[];
}

const props = defineProps<{ servers: ServerOption[]; history: Broadcast[] }>();

const { t } = useLocale();

const form = useForm<{ message: string; target: 'all' | 'selected'; server_ids: number[] }>({
    message: '',
    target: 'all',
    server_ids: [],
});

const reachable = computed(() => props.servers.filter((s) => s.bridge_enabled));
const chars = computed(() => form.message.length);

function toggleServer(id: number) {
    const i = form.server_ids.indexOf(id);
    if (i === -1) form.server_ids.push(id);
    else form.server_ids.splice(i, 1);
}

const canSend = computed(() =>
    form.message.trim().length > 0 &&
    (form.target === 'all' ? reachable.value.length > 0 : form.server_ids.length > 0),
);

function submit() {
    form.post(route('admin.servercast.store'), {
        preserveScroll: true,
        onSuccess: () => { form.reset('message', 'server_ids'); },
    });
}

function remove(id: number) {
    if (confirm(t('ext.servercast.confirm_delete'))) {
        router.delete(route('admin.servercast.destroy', id), { preserveScroll: true });
    }
}

const statusMeta: Record<string, { icon: unknown; class: string }> = {
    confirmed: { icon: CheckCircle2, class: 'text-emerald-400' },
    sent:      { icon: Send,         class: 'text-blue-400' },
    queued:    { icon: Clock,        class: 'text-amber-400' },
    expired:   { icon: XCircle,      class: 'text-red-400' },
    'no-bridge': { icon: WifiOff,    class: 'text-zinc-600' },
};

function serverName(id: number): string {
    return props.servers.find((s) => s.id === id)?.name ?? `#${id}`;
}
</script>

<template>
    <Head :title="t('ext.servercast.title')" />

    <AdminLayout>
        <PageHeader :title="t('ext.servercast.title')" :description="t('ext.servercast.subtitle')" :icon="Radio" />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Compose -->
            <div class="lg:col-span-2">
                <form
                    class="bg-[#111113] border border-zinc-800/70 rounded-2xl p-5"
                    @submit.prevent="submit"
                >
                    <h2 class="text-zinc-200 text-sm font-bold mb-4">{{ t('ext.servercast.compose') }}</h2>

                    <!-- Message -->
                    <label class="block text-zinc-500 text-xs font-medium mb-1.5">{{ t('ext.servercast.message') }}</label>
                    <textarea
                        v-model="form.message"
                        rows="3"
                        maxlength="400"
                        :placeholder="t('ext.servercast.message_placeholder')"
                        class="w-full bg-zinc-900/60 border border-zinc-800 text-zinc-100 rounded-xl px-3.5 py-2.5 text-sm placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 transition resize-none"
                    />
                    <div class="flex justify-between mt-1">
                        <p v-if="form.errors.message" class="text-red-400 text-xs">{{ form.errors.message }}</p>
                        <span class="text-zinc-700 text-[11px] ml-auto tabular-nums">{{ chars }}/400</span>
                    </div>

                    <!-- Target -->
                    <label class="block text-zinc-500 text-xs font-medium mb-2 mt-4">{{ t('ext.servercast.target') }}</label>
                    <div class="flex gap-2 mb-3">
                        <button
                            type="button"
                            class="flex-1 rounded-xl border px-3 py-2 text-sm font-medium transition"
                            :class="form.target === 'all'
                                ? 'bg-blue-500/15 border-blue-500/40 text-blue-300'
                                : 'bg-zinc-900/60 border-zinc-800 text-zinc-400 hover:text-zinc-200'"
                            @click="form.target = 'all'"
                        >{{ t('ext.servercast.all_servers') }}</button>
                        <button
                            type="button"
                            class="flex-1 rounded-xl border px-3 py-2 text-sm font-medium transition"
                            :class="form.target === 'selected'
                                ? 'bg-blue-500/15 border-blue-500/40 text-blue-300'
                                : 'bg-zinc-900/60 border-zinc-800 text-zinc-400 hover:text-zinc-200'"
                            @click="form.target = 'selected'"
                        >{{ t('ext.servercast.pick_servers') }}</button>
                    </div>

                    <!-- Server picker -->
                    <div v-if="form.target === 'selected'" class="space-y-1.5 mb-4">
                        <p v-if="!servers.length" class="text-zinc-600 text-xs py-3 text-center">{{ t('ext.servercast.no_servers') }}</p>
                        <button
                            v-for="s in servers"
                            :key="s.id"
                            type="button"
                            :disabled="!s.bridge_enabled"
                            class="w-full flex items-center gap-3 rounded-xl border px-3 py-2 text-left transition disabled:opacity-40 disabled:cursor-not-allowed"
                            :class="form.server_ids.includes(s.id)
                                ? 'bg-blue-500/10 border-blue-500/30'
                                : 'bg-zinc-900/40 border-zinc-800 hover:border-zinc-700'"
                            @click="s.bridge_enabled && toggleServer(s.id)"
                        >
                            <div class="w-4 h-4 rounded border flex items-center justify-center shrink-0"
                                :class="form.server_ids.includes(s.id) ? 'bg-blue-500 border-blue-500' : 'border-zinc-700'">
                                <CheckCircle2 v-if="form.server_ids.includes(s.id)" :size="12" class="text-white" />
                            </div>
                            <GameIcon v-if="s.game_slug" :slug="s.game_slug" :size="32" img-class="w-6 h-6 rounded object-contain shrink-0" />
                            <div class="min-w-0 flex-1">
                                <p class="text-zinc-200 text-sm font-medium truncate">{{ s.name }}</p>
                                <p class="text-zinc-600 text-[11px] font-mono truncate">{{ s.address }}</p>
                            </div>
                            <span v-if="!s.bridge_enabled" class="text-zinc-600 text-[10px]">{{ t('ext.servercast.no_bridge') }}</span>
                            <span v-else-if="s.online" class="w-1.5 h-1.5 rounded-full bg-emerald-500" :title="t('ext.servercast.online')" />
                            <span v-else class="w-1.5 h-1.5 rounded-full bg-zinc-700" :title="t('ext.servercast.offline')" />
                        </button>
                    </div>
                    <p v-if="form.errors.server_ids" class="text-red-400 text-xs mb-3">{{ form.errors.server_ids }}</p>

                    <button
                        type="submit"
                        :disabled="!canSend || form.processing"
                        class="w-full inline-flex items-center justify-center gap-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition shadow-md shadow-blue-500/20 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <Send :size="15" :stroke-width="2" />
                        {{ form.processing ? t('ext.servercast.sending') : t('ext.servercast.send') }}
                    </button>

                    <p class="text-zinc-700 text-[11px] mt-3 text-center">
                        {{ t('ext.servercast.reachable_summary', { reachable: reachable.length, total: servers.length }) }}
                    </p>
                </form>
            </div>

            <!-- History -->
            <div class="lg:col-span-1">
                <div class="bg-[#111113] border border-zinc-800/70 rounded-2xl p-5">
                    <div class="flex items-center gap-2 mb-4">
                        <History :size="15" :stroke-width="1.75" class="text-zinc-500" />
                        <h2 class="text-zinc-200 text-sm font-bold">{{ t('ext.servercast.history') }}</h2>
                    </div>

                    <p v-if="!history.length" class="text-zinc-600 text-xs py-6 text-center">{{ t('ext.servercast.no_history') }}</p>

                    <div v-else class="space-y-3">
                        <div v-for="b in history" :key="b.id" class="group border border-zinc-800/60 rounded-xl p-3">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-zinc-200 text-sm leading-snug break-words">{{ b.message }}</p>
                                <button type="button" class="text-zinc-700 hover:text-red-400 transition opacity-0 group-hover:opacity-100 shrink-0" @click="remove(b.id)">
                                    <Trash2 :size="13" :stroke-width="1.75" />
                                </button>
                            </div>
                            <p class="text-zinc-600 text-[11px] mt-1">
                                <span v-if="b.sent_by">{{ b.sent_by }} · </span>{{ b.created_at }}
                            </p>
                            <div class="flex flex-wrap gap-1.5 mt-2">
                                <span
                                    v-for="d in b.deliveries"
                                    :key="d.server_id"
                                    class="inline-flex items-center gap-1 text-[10px] rounded-md bg-zinc-900/70 border border-zinc-800 px-1.5 py-0.5"
                                    :title="serverName(d.server_id)"
                                >
                                    <component :is="(statusMeta[d.status] || statusMeta.queued).icon" :size="10" :class="(statusMeta[d.status] || statusMeta.queued).class" />
                                    <span class="text-zinc-400 truncate max-w-[7rem]">{{ serverName(d.server_id) }}</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
