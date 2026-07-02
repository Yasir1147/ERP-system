<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Building2, CalendarCheck2, LoaderCircle, ShieldCheck } from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log in" />

    <main class="min-h-svh bg-[#f5f6f8] text-foreground">
        <div class="grid min-h-svh lg:grid-cols-2">
            <section class="relative hidden overflow-hidden bg-white text-[#111827] lg:block">
                <img
                    src="/login-rope-construction-grid.png"
                    alt=""
                    aria-hidden="true"
                    class="absolute inset-0 h-full w-full object-cover opacity-55 saturate-75"
                />
                <div class="absolute inset-0 bg-white/68" />
                <div class="absolute inset-0 bg-gradient-to-br from-white/95 via-white/84 to-white/72" />
                <div class="absolute inset-0 bg-gradient-to-t from-white/90 via-transparent to-white/58" />
                <div class="absolute left-[-10%] top-[-8%] h-64 w-64 rounded-full border border-slate-900/12" />
                <div class="absolute bottom-[-12%] right-[-8%] h-80 w-80 rounded-full border border-slate-900/12" />

                <div class="relative z-10 flex min-h-svh flex-col p-10 xl:p-12">
                    <Link :href="route('home')" class="flex w-fit items-center gap-3">
                        <AppLogoIcon class="size-14 rounded-full bg-white p-1 shadow-sm" />
                        <div class="drop-shadow-sm">
                            <p class="text-lg font-semibold leading-tight">Al Mohafiz</p>
                            <p class="text-sm font-medium text-slate-700">Building Contracting L.L.C.</p>
                        </div>
                    </Link>

                    <div class="relative my-auto overflow-hidden">
                        <div class="relative flex min-h-[560px] flex-col justify-center p-8 xl:min-h-[620px] xl:p-10">
                            <p class="text-sm font-bold uppercase tracking-[0.18em] text-emerald-700 drop-shadow-sm">Attendance System</p>
                            <h1 class="mt-5 max-w-xl text-5xl font-bold leading-tight tracking-normal text-slate-950">
                                Workforce attendance, payroll, and project control.
                            </h1>
                            <p class="mt-5 max-w-lg text-base font-semibold leading-7 text-slate-800">
                                Manage daily attendance, overtime, leave records, project history, and payroll reports from one local system.
                            </p>

                            <div class="mt-10 grid max-w-2xl gap-3 sm:grid-cols-3">
                                <div class="rounded-lg border border-slate-200/90 border-t-4 border-t-emerald-500 bg-white/92 p-4 shadow-[0_12px_30px_rgba(15,23,42,0.12)] backdrop-blur-md">
                                    <CalendarCheck2 class="size-5 text-emerald-700" />
                                    <p class="mt-3 text-sm font-semibold text-slate-950">Attendance</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-600">Daily records</p>
                                </div>
                                <div class="rounded-lg border border-slate-200/90 border-t-4 border-t-sky-500 bg-white/92 p-4 shadow-[0_12px_30px_rgba(15,23,42,0.12)] backdrop-blur-md">
                                    <Building2 class="size-5 text-sky-700" />
                                    <p class="mt-3 text-sm font-semibold text-slate-950">Projects</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-600">Cost tracking</p>
                                </div>
                                <div class="rounded-lg border border-slate-200/90 border-t-4 border-t-amber-500 bg-white/92 p-4 shadow-[0_12px_30px_rgba(15,23,42,0.12)] backdrop-blur-md">
                                    <ShieldCheck class="size-5 text-amber-700" />
                                    <p class="mt-3 text-sm font-semibold text-slate-950">Payroll</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-600">Payslips</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-sm font-semibold text-slate-700">Local access for authorized users only.</div>
                </div>
            </section>

            <section class="flex min-h-svh items-center justify-center px-5 py-10 sm:px-8">
                <div class="w-full max-w-[430px]">
                    <div class="mb-8 flex flex-col items-center text-center lg:hidden">
                        <AppLogoIcon class="size-24" />
                        <p class="mt-3 text-lg font-semibold">Al Mohafiz</p>
                        <p class="text-sm text-muted-foreground">Building Contracting L.L.C.</p>
                    </div>

                    <div class="rounded-lg border bg-white p-6 shadow-sm sm:p-8">
                        <div class="text-center">
                            <AppLogoIcon class="mx-auto hidden size-24 lg:block" />
                            <h1 class="mt-5 text-2xl font-semibold tracking-normal">Log in to your account</h1>
                            <p class="mt-2 text-sm text-muted-foreground">Attendance users can enter username only</p>
                        </div>

                        <div v-if="status" class="mt-5 rounded-md border border-green-600/20 bg-green-600/10 px-3 py-2 text-center text-sm font-medium text-green-700">
                            {{ status }}
                        </div>

                        <form @submit.prevent="submit" class="mt-7 flex flex-col gap-6">
                            <div class="grid gap-5">
                                <div class="grid gap-2">
                                    <Label for="email">Email or username</Label>
                                    <Input
                                        id="email"
                                        type="text"
                                        required
                                        autofocus
                                        tabindex="1"
                                        autocomplete="username"
                                        v-model="form.email"
                                        placeholder="username or email"
                                    />
                                    <InputError :message="form.errors.email" />
                                </div>

                                <div class="grid gap-2">
                                    <div class="flex items-center justify-between">
                                        <Label for="password">Password for admin</Label>
                                        <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-sm" tabindex="5"> Forgot password? </TextLink>
                                    </div>
                                    <Input
                                        id="password"
                                        type="password"
                                        tabindex="2"
                                        autocomplete="current-password"
                                        v-model="form.password"
                                        placeholder="Admin password"
                                    />
                                    <InputError :message="form.errors.password" />
                                </div>

                                <div class="flex items-center justify-between" tabindex="3">
                                    <Label for="remember" class="flex items-center space-x-3">
                                        <Checkbox id="remember" v-model:checked="form.remember" tabindex="4" />
                                        <span>Remember me</span>
                                    </Label>
                                </div>

                                <Button type="submit" class="h-11 w-full" tabindex="4" :disabled="form.processing">
                                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                                    Log in
                                </Button>
                            </div>

                        </form>
                    </div>
                </div>
            </section>
        </div>
    </main>
</template>
