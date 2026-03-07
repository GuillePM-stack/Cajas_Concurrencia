import './bootstrap';
import { createApp, ref, onMounted, onUnmounted } from 'vue';

createApp({
    setup() {
        const caja = ref(1);
        const listaCajas = ref([]);
        const importe = ref('');
        const billetes = ref([]);
        const cajaAbierta = ref(false);
        const disabledAbrir = ref(false);
        const sidebarAbierto = ref(true);
        const procesando = ref(false); 

        const actualizarTodo = async () => {
            if (!caja.value) return;
            try {
                const resD = await fetch(`/caja/${caja.value}`);
                const dataD = await resD.json();

                if (dataD.bloqueado) {
                    disabledAbrir.value = true;
                } else if (dataD.abierta) {
                    billetes.value = dataD.data;
                    cajaAbierta.value = true;
                    disabledAbrir.value = true;
                } else {
                    billetes.value = [];
                    cajaAbierta.value = false;
                    disabledAbrir.value = false;
                }

                const resC = await fetch('/cajas');
                const dataC = await resC.json();
                listaCajas.value = dataC.data;
            } catch (e) { console.error("Error de sincronización", e); }
        };

        const seleccionarCaja = async (numero) => {
            caja.value = numero;
            importe.value = '';
            await actualizarTodo();
        };

        const crearNuevaCaja = async () => {
            if (procesando.value) return;
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            let nueva = Math.max(...listaCajas.value, 0) + 1;

            try {
                await fetch(`/registrar-caja/${nueva}`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token }
                });
                await actualizarTodo();
            } catch (e) { console.error(e); }
        };

        const abrirCaja = async () => {
            if (procesando.value) return;
            procesando.value = true;
            disabledAbrir.value = true;
            try {
                const response = await fetch(`/abrir-caja/${caja.value}`);
                if (response.status === 423) {
                    alert("⚠️ SISTEMA OCUPADO: Alguien más está operando la bóveda.");
                    disabledAbrir.value = false;
                    return;
                }
                const data = await response.json();
                if (!data.error) {
                    billetes.value = data.data;
                    cajaAbierta.value = true;
                } else { disabledAbrir.value = false; }
            } catch (e) {
                disabledAbrir.value = false;
            } finally {
                procesando.value = false;
            }
        };

        const agregarBilletes = async () => {
            if (procesando.value) return;
            if (!importe.value || importe.value <= 0) {
                return alert('Ingresa una cantidad válida');
            }

            procesando.value = true; // Bloquear acciones
            try {
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const response = await fetch(`/agregar-billetes/${caja.value}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({ importe: importe.value })
                });

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    alert('Error en el servidor. Revisa la consola.');
                    return;
                }

                if (response.status === 423) {
                    const data = await response.json();
                    return alert(data.mensaje || "Bodega ocupada. Intenta de nuevo.");
                }

                const data = await response.json();

                if (!response.ok) {
                    alert(data.mensaje || 'Error en la solicitud');
                    return;
                }

                if (!data.error) {
                    billetes.value = data.data;
                    importe.value = '';
                    alert(data.mensaje || 'Operación exitosa');
                } else {
                    alert(data.mensaje);
                }

                await new Promise(resolve => setTimeout(resolve, 1500));
            } catch (e) {
                console.error('Error en agregarBilletes:', e);
                alert('Ocurrió un error inesperado. Revisa la consola.');
            } finally {
                procesando.value = false; 
            }
        };

        const cambiarCheque = async () => {
            if (procesando.value) return;
            if (!importe.value || importe.value <= 0) return alert('Importe inválido');

            procesando.value = true; 
            try {
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const response = await fetch(`/cambiar-cheque/${caja.value}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ importe: importe.value })
                });

                if (response.status === 423) return alert("⚠️ BODEGA OCUPADA. Intenta de nuevo.");

                const data = await response.json();
                if (!data.error) {
                    billetes.value = data.data;
                    importe.value = '';
                    alert("Éxito");
                } else {
                    alert(data.mensaje);
                }

                await new Promise(resolve => setTimeout(resolve, 1500));
            } catch (e) {
                console.error(e);
            } finally {
                procesando.value = false;
            }
        };

        let intervalo = null;
        onMounted(() => {
            actualizarTodo();
            intervalo = setInterval(actualizarTodo, 2000);
        });

        onUnmounted(() => { clearInterval(intervalo); });

        return {
            caja, listaCajas, importe, billetes, cajaAbierta, disabledAbrir, procesando,
            seleccionarCaja, crearNuevaCaja, abrirCaja, agregarBilletes, cambiarCheque, sidebarAbierto
        };
    }
}).mount('#app');