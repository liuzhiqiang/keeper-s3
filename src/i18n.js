import i18n from 'i18next';
import {initReactI18next} from 'react-i18next';
import en from './locales/en.json';
import zh from './locales/zh.json';

i18n
    .use(initReactI18next)
    .init({
        resources: {
            en_US: {
                translation: en
            },
            zh_CN: {
                translation: zh
            }
        },
        lng: s3keeper_ajax_object.locale, // 默认语言
        fallbackLng: 'en_US', // 回退语言
        interpolation: {
            escapeValue: false // react already safes from xss
        }
    });

export default i18n;