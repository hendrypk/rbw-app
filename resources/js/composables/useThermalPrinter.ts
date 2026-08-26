// // resources/js/composables/useThermalPrinter.ts
// import { ref } from 'vue';

// interface BluetoothDevice {
//   id: string;
//   name?: string;
//   gatt?: BluetoothRemoteGATTServer;
// }

// interface BluetoothRemoteGATTServer {
//   connect(): Promise<BluetoothRemoteGATTServer>;
//   disconnect(): void;
//   connected: boolean;
//   getPrimaryService(service: string | number): Promise<BluetoothRemoteGATTService>;
// }

// interface BluetoothRemoteGATTService {
//   getCharacteristic(characteristic: string | number): Promise<BluetoothRemoteGATTCharacteristic>;
// }

// interface BluetoothRemoteGATTCharacteristic {
//   writeValue(value: BufferSource): Promise<void>;
// }

// interface Bluetooth {
//   requestDevice(options?: {
//     filters?: Array<{ services?: Array<string | number>; name?: string; namePrefix?: string }>;
//     acceptAllDevices?: boolean;
//     optionalServices?: Array<string | number>;
//   }): Promise<BluetoothDevice>;
//   requestAllDevices?(options?: any): Promise<BluetoothDevice>;
// }

// const connectedDeviceName = ref<string>(localStorage.getItem('connected_printer_name') || '');
// const availableDevices = ref<BluetoothDevice[]>([]);
// const isScanning = ref<boolean>(false);

// export function useThermalPrinter() {
//   let bluetoothDevice: BluetoothDevice | null = null;
//   let characteristic: BluetoothRemoteGATTCharacteristic | null = null;

//   // Fitur Scan / Menemukan Perangkat Sekitar
// const scanDevices = async () => {
//     isScanning.value = true;
//     availableDevices.value = [];
//     try {
//       const nav = navigator as Navigator & { bluetooth?: Bluetooth };
//       if (!nav.bluetooth) {
//         throw new Error("Browser tidak mendukung Web Bluetooth.");
//       }

//       // Menggunakan filter agar pop-up browser tidak menampilkan semua perangkat ngawur
//       const device = await nav.bluetooth.requestDevice({
//         filters: [
//           { services: ['000018f0-0000-1000-8000-00805f9b34fb'] }, // UUID umum printer thermal
//           { namePrefix: 'RP' },    // Contoh printer kasir sering berawalan RP (RPP02, dll)
//           { namePrefix: 'POS' },   // Atau berawalan POS
//           { namePrefix: 'Printer' }
//         ],
//         optionalServices: ['000018f0-0000-1000-8000-00805f9b34fb']
//       });

//       if (device && !availableDevices.value.some(d => d.id === device.id)) {
//         availableDevices.value.push(device);
//       }
//     } catch (error) {
//       console.error("Scanning dibatalkan atau gagal:", error);
//     } finally {
//       isScanning.value = false;
//     }
//   };

//   // Menghubungkan langsung ke perangkat yang dipilih dari daftar
//   const connectToDevice = async (device: BluetoothDevice): Promise<boolean> => {
//     try {
//       bluetoothDevice = device;
//       const server = await bluetoothDevice.gatt?.connect();
//       if (!server) throw new Error("Gagal terhubung ke GATT Server");

//       const service = await server.getPrimaryService('000018f0-0000-1000-8000-00805f9b34fb');
//       characteristic = await service.getCharacteristic('00002af1-0000-1000-8000-00805f9b34fb');

//       const deviceName = bluetoothDevice.name || "Thermal Printer";
//       connectedDeviceName.value = deviceName;
//       localStorage.setItem('connected_printer_name', deviceName);

//       return true;
//     } catch (error) {
//       console.error("Gagal menghubungkan ke perangkat:", error);
//       throw error;
//     }
//   };

//   // Metode koneksi bawaan (Dialog standar browser)
//   const connectPrinter = async (): Promise<boolean> => {
//     try {
//       const nav = navigator as Navigator & { bluetooth?: Bluetooth };
//       if (!nav.bluetooth) throw new Error("Browser tidak mendukung Web Bluetooth.");

//       bluetoothDevice = await nav.bluetooth.requestDevice({
//         filters: [{ services: ['000018f0-0000-1000-8000-00805f9b34fb'] }],
//         acceptAllDevices: false
//       });

//       return await connectToDevice(bluetoothDevice);
//     } catch (error) {
//       console.error("Gagal menghubungkan printer:", error);
//       throw error;
//     }
//   };

//   const print = async (rawText: string): Promise<void> => {
//     try {
//       if (characteristic) {
//         const encoder = new TextEncoder();
//         const data = encoder.encode(rawText + "\n\n\n");
//         await characteristic.writeValue(data);
//         return;
//       }
//       printViaRawBTOrBrowser(rawText);
//     } catch (error) {
//       console.warn("Gagal kirim via bluetooth aktif, beralih ke fallback...", error);
//       printViaRawBTOrBrowser(rawText);
//     }
//   };

//   const printViaRawBTOrBrowser = (text: string) => {
//     try {
//       const base64Data = btoa(unescape(encodeURIComponent(text)));
//       window.location.href = `rawbt:data:base64,${base64Data}`;
//     } catch (e) {
//       const printWindow = window.open('', '_blank', 'width=400,height=600');
//       if (printWindow) {
//         printWindow.document.write(`<html><body><pre>${text}</pre><script>window.print();window.close();</script></body></html>`);
//         printWindow.document.close();
//       }
//     }
//   };

//   const disconnectPrinter = () => {
//     if (bluetoothDevice && bluetoothDevice.gatt?.connected) {
//       bluetoothDevice.gatt.disconnect();
//     }
//     connectedDeviceName.value = '';
//     localStorage.removeItem('connected_printer_name');
//     characteristic = null;
//     bluetoothDevice = null;
//   };

//   return {
//     connectedDeviceName,
//     availableDevices,
//     isScanning,
//     scanDevices,
//     connectToDevice,
//     connectPrinter,
//     disconnectPrinter,
//     print
//   };
// }
// resources/js/composables/useThermalPrinter.ts
import { ref } from 'vue';

const connectedDeviceName = ref<string>(localStorage.getItem('connected_printer_name') || 'Direct Print Bridge');

export function useThermalPrinter() {
  
  // Fungsi cetak langsung melempar data via Intent/Base64 tanpa mengunci Bluetooth fisik
  const print = async (rawText: string): Promise<void> => {
    try {
      const base64Data = btoa(unescape(encodeURIComponent(rawText)));
      // Melempar ke aplikasi perantara (Contoh: RawBT)
      window.location.href = `rawbt:data:base64,${base64Data}`;
    } catch (error) {
      console.warn("Gagal mengirim ke aplikasi cetak, gunakan fallback browser...", error);
      printViaBrowserFallback(rawText);
    }
  };

  const printViaBrowserFallback = (text: string) => {
    const printWindow = window.open('', '_blank', 'width=400,height=600');
    if (printWindow) {
      printWindow.document.write(`<html><body><pre style="font-family:monospace; font-size:12px;">${text}</pre><script>window.print();window.close();</script></body></html>`);
      printWindow.document.close();
    }
  };

  const disconnectPrinter = () => {
    connectedDeviceName.value = '';
    localStorage.removeItem('connected_printer_name');
  };

  return {
    connectedDeviceName,
    print,
    disconnectPrinter
  };
}