import dayjs from 'dayjs';
import dayjsHijri from 'dayjs-hijri';

dayjs.extend(dayjsHijri);

window.dayjs = dayjs;
