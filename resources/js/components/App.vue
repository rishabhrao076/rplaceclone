<script setup>
import axios from 'axios';
import { ref, onMounted, computed } from 'vue';
import Modal from './Modal.vue';
import Timer from './Timer.vue';

const map = ref([]);
const canvasRef = ref(null);
const activePixel = ref(null);
const SCROLL_ZOOM_SENSITIVITY = 0.002;
const MAX_ZOOM = 5; //every integer step is a level
const MIN_ZOOM = 1;
const dragStart = { x: 0, y: 0 }
const cameraOffset = { x: 0, y: 0 }
const transformationData = { scale: 1, xTranslate: 0, yTranslate: 0 }

let modalMessage = ref('');
let isDragging = false;
let currentZoom = ref(1);
let overlayLocationX = ref(0);
let overlayLocationY = ref(0);

let showModal = ref(false);
let showTimer = ref(false);
let timerTime = ref(0);
const activePixelEmail = computed(() => {
    if (!activePixel.value) {
        return '';
    }
    let [x, y] = activePixel.value.split(':');
    return map.value[x][y].split(':')[1];
});

const pixelWidth = computed(() => {
    return 8 * currentZoom.value;
})

const canvasWidth = computed(() => {
    return 100 * pixelWidth.value;
})

const overlayStyle = computed(() => {
    return { top: `${overlayLocationY.value}px`, left: `${overlayLocationX.value}px`, width: `${pixelWidth.value}px`, height: `${pixelWidth.value}px` }
})

const colorOptions = {
    red: '231, 76, 60',
    orange: '230, 126, 34',
    yellow: '241, 196, 15',
    green: '46, 204, 113',
    blue: '52, 152, 219',
    purple: '155, 89, 182',
    white: '236, 240, 241',
    black: '44, 62, 80',
    brown: '150, 75, 0',
    cyan: '0, 100, 100',
    pink: '255, 192, 203',
}


const drawCanvas = () => {
    let context = canvasRef.value.getContext('2d');
    context.clearRect(0, 0, canvasRef.value.width, canvasRef.value.height);
    context.imageSmoothingEnabled = false;

    // Add Panning Limits on the canvas
    if (transformationData.xTranslate > 0) {
        transformationData.xTranslate = 0;
    }
    if (transformationData.xTranslate < -canvasWidth.value + 800) {
        transformationData.xTranslate = -canvasWidth.value + 800;
    }
    if (transformationData.yTranslate > 0) {
        transformationData.yTranslate = 0;
    }
    if (transformationData.yTranslate < -canvasWidth.value + 800) {
        transformationData.yTranslate = -canvasWidth.value + 800;
    }

    context.setTransform(currentZoom.value, 0, 0, currentZoom.value, transformationData.xTranslate, transformationData.yTranslate)

    //Update Global State
    transformationData.scale = currentZoom.value
    transformationData.xTranslate = context.getTransform().e
    transformationData.yTranslate = context.getTransform().f
    cameraOffset.x = Math.floor(context.getTransform().e % pixelWidth.value);
    cameraOffset.y = Math.floor(context.getTransform().f % pixelWidth.value);

    // for each approx 14ms
    map.value.forEach((column, x) => {
        column.forEach((pixel, y) => {
            let [colorIndex, email] = pixel.split(':');

            context.fillStyle = `rgb(${colorOptions[Object.keys(colorOptions)[colorIndex]]})`;
            context.fillRect(x * 8, y * 8, 8, 8)

        });
    });

}

const render = () => { requestAnimationFrame(drawCanvas) };

onMounted(async () => {
    const { data: pixelMap } = await axios.get("/map");
    map.value = pixelMap;

    // Initiating Web-socket connection
    Echo.channel('changes').listen('ColorChanged', (e) => {
        let [colorIndex, email] = e.change.color.split(':');

        let [x, y] = e.change.key.split(':');
        // convert string to number
        x = Number(x);
        y = Number(y);

        map.value[x][y] = `${colorIndex}:${email}`;

        render();
    });

    drawCanvas();

});

const canvasClicked = (e) => {

    //Clicked Element
    let clickedX = Math.ceil((e.layerX - pixelWidth.value - cameraOffset.x) / pixelWidth.value);
    let clickedY = Math.ceil((e.layerY - pixelWidth.value - cameraOffset.y) / pixelWidth.value);

    //Overlay Position,
    overlayLocationY.value = (clickedY * pixelWidth.value) + cameraOffset.y;
    overlayLocationX.value = (clickedX * pixelWidth.value) + cameraOffset.x;

    //Calculate actual active pixel here
    let activePixelX = Math.floor(clickedX - (transformationData.xTranslate / pixelWidth.value));
    let activePixelY = Math.floor(clickedY - (transformationData.yTranslate / pixelWidth.value));

    activePixel.value = `${activePixelX}:${activePixelY}`;


}

const beginPanning = (e) => {

    isDragging = true;

    //drag start values to subtract later-on to get integer values to translate
    dragStart.x = e.layerX
    dragStart.y = e.layerY
}

const handlePanning = (e) => {
    //Using requestAnimationFrame to throttle function calls
    //trigger rerender only-if distance dragged is more than 8px
    if (isDragging) {
        if (Math.abs(e.layerX - dragStart.x) >= 8 || Math.abs(e.layerY - dragStart.y) >= 8) {

            let context = canvasRef.value.getContext('2d');

            let translateX = e.layerX - dragStart.x
            let translateY = e.layerY - dragStart.y

            transformationData.xTranslate += translateX;
            transformationData.yTranslate += translateY;

            activePixel.value = null;

            render();

            dragStart.x = e.layerX
            dragStart.y = e.layerY

        }
    }


}

const stopPanning = (e) => {

    isDragging = false;
}

const changePixel = async (color) => {

    await axios.post('/save', { key: activePixel.value, color: Object.keys(colorOptions).indexOf(color) })
        .then((e) => {
            timerTime.value = 120;
            showTimer.value = true;
        })
        .catch((e) => {
            switch (e.response?.status) {
                case 429:
                    modalMessage.value = "Only one Attempt per 2 minutes";
                    timerTime.value = Number(e.response.headers['retry-after']);
                    showTimer.value = true;
                    break;
                case 401:
                    modalMessage.value = "Register/Login to participate and play"
                    break;
                default:
                    modalMessage.value = "Server Error"
                    break;
            }
            showModal.value = true;
        });

};

// handle zoom using mousewheel
const zoom = (e) => {
    //Remove overlay
    activePixel.value = null;

    const newScale = currentZoom.value + e.deltaY * SCROLL_ZOOM_SENSITIVITY;
    currentZoom.value = Math.min(Math.max(newScale, MIN_ZOOM), MAX_ZOOM);
    render();

}


</script>
<template>
    <div class="grid grid-cols-1 place-items-center h-full overflow-scroll md:overflow-hidden">
        <span class="text-md dark:text-white mb-2">Maker Portfolio:
            <a class="underline text-blue-500" href="https://my-portfolio-rishabhrao076.vercel.app/"
                target="_blank">Rishabh's Portfolio
            </a>
        </span>

        <h4 class="text-lg dark:text-white mb-4 z-10">Instructions: Right Click and drag to Pan, Scroll to Zoom</h4>
        <div class="relative w-[800px]">
            <Timer :secondsLimit="timerTime" v-if="showTimer" @secondPassed="timerTime -= 1"
                @completed="showTimer = false" />
        </div>
        <div class="relative dark:shadow-none dark:border-0 shadow-lg border overflow-hidden">


            <canvas width="800" height="800" ref="canvasRef" @wheel.prevent="zoom" @contextmenu.prevent
                @click="canvasClicked" @mousedown.right="beginPanning" @mousemove="handlePanning"
                @mouseup.right="stopPanning" @mouseleave="stopPanning"></canvas>
            <div id="overlay" class="absolute shadow-md shadow-white border border-solid border-black z-[99]"
                v-if="activePixel" :style="overlayStyle">
            </div>
        </div>

    </div>

    <div v-if="activePixel" id="colorPicker" class="fixed bottom-0 left-0 bg-white w-full p-4 text-center">
        <p class="mb-3">Last Changed by: <strong>{{ activePixelEmail }}</strong></p>
        <nav class="flex justify-center gap-2 w-full">
            <button v-for="color in Object.keys(colorOptions)" :key="color" @click="changePixel(color)"
                class="hover:cursor-pointer hover:opacity-75 border-2 border-black w-12 rounded p-2"
                :style="{ background: `rgb(${colorOptions[color]})` }">

            </button>
        </nav>
    </div>
    <Modal :show="showModal" @close="showModal = false">{{ modalMessage }}</Modal>
</template>
<style>
canvas {
    image-rendering: pixelated;

}
</style>