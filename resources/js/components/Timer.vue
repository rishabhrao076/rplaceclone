<script setup>

const props = defineProps({
    show: Boolean,
    secondsLimit: Number,
});

const emit = defineEmits(['completed', 'secondPassed'])

let timer = setInterval(() => {
    if (props.secondsLimit > 1) {
        emit('secondPassed')

    } else {
        clearInterval(timer);
        emit('completed')
    }
}, 1000)

</script>
<template>
    <div class="absolute -top-14 left-0  w-full flex justify-end">
        <div class="bg-white text-black dark:text-white dark:bg-gray-800 rounded-lg text-center py-2 px-4 w-20 shadow-lg">
            {{ Math.floor(secondsLimit / 60) }} : {{ (secondsLimit % 60) >= 10 ? secondsLimit % 60 : '0' + secondsLimit % 60
            }}
        </div>
    </div>
</template>