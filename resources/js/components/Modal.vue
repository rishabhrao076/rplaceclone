<script setup>
import InfoFill from '../logos/InfoFill.vue';

defineProps({
    show: Boolean,
});
defineEmits(
    ['close']
);
</script>
<template>
    <Teleport to="body">
        <Transition enter-from-class="opacity-0" enter-to-class="opacity-100" enter-active-class="transition duration-300"
            leave-from-class="opacity-100" leave-to-class="opacity-0" leave-active-class="transition duration-150">
            <div class="fixed w-full h-full inset-0 grid grid-cols-1 place-items-center z-[999] bg-opacity-40 bg-black "
                v-if="show" @click="$emit('close')">
                <div class="bg-white dark:bg-gray-800 dark:text-white p-4 rounded-lg">
                    <div class="flex flex-col items-center">
                        <InfoFill class="flex-1 my-2 h-8 w-8" />
                        <span class="text-lg my-2">
                            <slot></slot>
                        </span>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>