<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import { ref, onMounted } from "vue";
import GuestLayout from "@/Layouts/GuestLayout.vue";
import InputLabel from "@/Components/Inputs/InputLabel.vue";
import TextInput from "@/Components/Inputs/TextInput.vue";
import InputError from "@/Components/Inputs/InputError.vue";
import PrimaryButton from "@/Components/Buttons/PrimaryButton.vue";
import Checkbox from "@/Components/Inputs/Checkbox.vue";

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: "",
    password: "",
    remember: false,
});
const emailInput = ref(null);
const submit = () => {
    form.post(route("login"), {
        onFinish: () => form.reset("password"),
    });
};
onMounted(() => {
    emailInput.value?.focus();
});
</script>

<template>
    <GuestLayout>
        <Head title="Login" />

        <!-- Formulario -->
        <form @submit.prevent="submit" class="space-y-6">
            <!-- Usuario -->
            <div>
                <InputLabel for="email" value="Usuario" class="colorMorado" />
                <TextInput
                    id="email"
                    type="email"
                    ref="emailInput"
                    class="mt-1 block w-full inputs"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <!-- Contraseña -->
            <div>
                <InputLabel
                    for="password"
                    value="Contraseña"
                    class="colorMorado"
                />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full inputs"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <!-- Recordarme -->
            <div class="flex items-center">
                <Checkbox name="remember" v-model:checked="form.remember" />
                <span class="ml-2 text-sm text-gray-600">Recuérdame</span>
            </div>

            <!-- Botón -->
            <div class="text-center">
                <PrimaryButton
                    class="btn btnFull"
                    :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing"
                >
                    {{ form.processing ? "Ingresando..." : "Ingresar" }}
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
