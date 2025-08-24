import axios from "axios";
import {toast, ToastContainer} from "react-toastify";
import React, {useState, useEffect} from 'react';
import {styled} from '@mui/system';
import Visibility from '@mui/icons-material/Visibility';
import VisibilityOff from '@mui/icons-material/VisibilityOff';
import {
    Button,
    Container,
    FormControl, FormLabel, RadioGroup, Radio, FormHelperText, FormControlLabel,
    InputLabel,
    LinearProgress,
    MenuItem,
    Select,
    TextField,
    Typography, Box, Grid, InputAdornment, IconButton, Alert
} from "@mui/material";
import {
    Dialog,
    DialogTitle,
    DialogContent,
    DialogActions,
} from '@mui/material';

import {useTranslation} from 'react-i18next'; // 引入 useTranslation

const Code = styled('code')({
    backgroundColor: 'rgba(0, 0, 0, 0.05)',
    padding: '2px 4px',
    borderRadius: '4px',
    fontFamily: 'monospace'
});

const FormWrapper = styled('div')({
    margin: '0 auto',
});


const Setting = () => {
    const {t} = useTranslation(); // 初始化 useTranslation hook

    const [loading, setLoading] = useState(false);
    const [isRequesting, setIsRequesting] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const [error, setError] = useState({isOpen: false, message: ''});

    const [settings, setSettings] = useState({
        s3_endpoint: '',
        s3_bucket: '',
        s3_key: '',
        s3_secret: '',
        s3_location: 'local',
        s3_url: '',
        s3_region: 'us-east-1',
        s3_acl: 'public-read',
        s3_path: 'uploads',
        s3_check_success: '',
    });


    const handleClickShowPassword = () => {
        setShowPassword(!showPassword);
    };

    useEffect(() => {
        setIsRequesting(true);
        axios.get(s3keeper_ajax_object.ajax_url, {
            params: {
                action: 's3keeper_get_settings',
            }
        })
            .then((response) => {
                const {data} = response;
                setSettings(data.data);
            }).finally(() => {
            setIsRequesting(false);
        });
    }, []);


    const handleInputChange = (e) => {
        const {name, value} = e.target;
        setSettings((prevSettings) => ({
            ...prevSettings,
            [name]: value,
        }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);

        var params = new URLSearchParams();
        params.append('action', 's3keeper_update_settings');
        params.append('_wpnonce', s3keeper_ajax_object.nonce);

        for (const key in settings) {
            if (settings.hasOwnProperty(key)) {
                params.append(key, settings[key]);
            }
        }

        try {
            const response = await axios.post(s3keeper_ajax_object.ajax_url, params);


            if (response.data && response.data.success) {

                const connection_status = response.data.data.connection_status;


                if (connection_status.success) {
                    settings.s3_check_success = 'yes';
                    toast.success(t('settingsSavedSuccess'));

                } else {
                    settings.s3_check_success = 'no';
                    toast.error(t('s3ConnectionFailed'));
                    setError({isOpen: true, message: connection_status.message});

                }
            } else {
                toast.error(t('errorSavingSettings'));
            }

        } catch (error) {
            let errorMessage = t('anErrorOccurred');
            if (error.response && error.response.data && error.response.data.message) {
                errorMessage = error.response.data.message;
            } else if (error.message) {
                errorMessage = error.message;
            }
            toast.error(errorMessage);
        } finally {
            setLoading(false);
        }
    };
    const closeErrorDialog = () => {
        setError({isOpen: false, message: ''});
    };

    return (
        <Container style={{marginTop: '20px'}}>
            {isRequesting && <LinearProgress/>}
            <Dialog open={error.isOpen} onClose={closeErrorDialog}>
                <DialogTitle>{t('error')}</DialogTitle>
                <DialogContent>
                    <pre style={{whiteSpace: 'pre-wrap', wordWrap: 'break-word'}}>{error.message}</pre>
                </DialogContent>
                <DialogActions>
                    <Button onClick={closeErrorDialog}>{t('close')}</Button>
                </DialogActions>
            </Dialog>

            <Typography variant="h4" gutterBottom>{t('s3KeeperSettings')}</Typography>
            {loading && <LinearProgress/>}
            {settings?.s3_check_success === 'no' && (
                <Alert severity="error">{t('currentS3ConfigFailed')}</Alert>
            )}

            <FormWrapper>
                <form onSubmit={handleSubmit}>

                    <Grid container spacing={2} alignItems="center">
                        <Grid item xs={12} sm={6}>
                            <TextField
                                label={t('s3EndpointLabel')}
                                name="s3_endpoint"
                                value={settings.s3_endpoint}
                                onChange={handleInputChange}
                                fullWidth
                                margin="normal"
                            />
                        </Grid>
                        <Grid item xs={12} sm={6}>
                            <Box ml={1}>
                                <Typography variant="body2" color="textSecondary">
                                    {t('s3EndpointDescription')}
                                </Typography>
                                <Typography variant="caption">
                                    {t('s3EndpointExample')}
                                </Typography>
                            </Box>
                        </Grid>
                    </Grid>


                    <Grid container spacing={2} alignItems="center">
                        <Grid item xs={12} sm={6}>
                            <TextField
                                label={t('s3BucketNameLabel')}
                                name="s3_bucket"
                                value={settings.s3_bucket}
                                onChange={handleInputChange}
                                fullWidth
                                margin="normal"
                            />
                        </Grid>
                        <Grid item xs={12} sm={6}>
                            <Box ml={1}>
                                <Typography variant="body2" color="textSecondary">
                                    {t('s3BucketNameDescription')}
                                </Typography>
                                <Typography variant="caption">
                                    {t('s3BucketNameExample')}
                                </Typography>
                            </Box>
                        </Grid>
                    </Grid>


                    <Grid container spacing={2} alignItems="center">
                        <Grid item xs={12} sm={6}>
                            <TextField
                                label={t('s3AccessKeyLabel')}
                                name="s3_key"
                                value={settings.s3_key}
                                onChange={handleInputChange}
                                fullWidth
                                margin="normal"
                            />
                        </Grid>
                        <Grid item xs={12} sm={6}>
                            <Box ml={1}>
                                <Typography variant="body2" color="textSecondary">
                                    {t('s3AccessKeyDescription')}
                                </Typography>
                                <Typography variant="caption">
                                    {t('s3AccessKeyExample')}
                                </Typography>
                            </Box>
                        </Grid>
                    </Grid>


                    <Grid container spacing={2} alignItems="center">
                        <Grid item xs={12} sm={6}>
                            <TextField
                                label={t('s3SecretKeyLabel')}
                                name="s3_secret"
                                type={showPassword ? 'text' : 'password'}
                                value={settings.s3_secret}
                                onChange={handleInputChange}
                                fullWidth
                                margin="normal"
                                InputProps={{
                                    endAdornment: (
                                        <InputAdornment position="end">
                                            <IconButton
                                                aria-label="toggle password visibility"
                                                onClick={handleClickShowPassword}
                                                edge="end"
                                            >
                                                {showPassword ? <VisibilityOff/> : <Visibility/>}
                                            </IconButton>
                                        </InputAdornment>
                                    ),
                                }}
                            />
                        </Grid>
                        <Grid item xs={12} sm={6}>
                            <Box ml={1}>
                                <Typography variant="body2" color="textSecondary">
                                    {t('s3SecretKeyDescription')}
                                </Typography>
                                <Typography variant="caption">
                                    {t('s3SecretKeyExample')}
                                </Typography>
                            </Box>
                        </Grid>
                    </Grid>

                    <Grid container spacing={2} alignItems="center">
                        <Grid item xs={12} sm={6}>
                            <TextField
                                label={t('s3RegionLabel')}
                                name="s3_region"
                                value={settings.s3_region}
                                onChange={handleInputChange}
                                fullWidth
                                margin="normal"
                            />
                        </Grid>
                        <Grid item xs={12} sm={6}>
                            <Box ml={1}>
                                <Typography variant="body2" color="textSecondary">
                                    {t('s3RegionDescription')}
                                </Typography>
                                <Typography variant="caption">
                                    {t('s3RegionExample')}
                                </Typography>
                            </Box>
                        </Grid>
                    </Grid>


                    <Grid item xs={12} sm={6}>
                        <FormControl component="fieldset" fullWidth margin="normal">
                            <FormLabel component="legend">{t('s3ACLLabel')}</FormLabel>
                            <RadioGroup
                                row // 设置为 row，保证 radio 按钮在一行显示
                                name="s3_acl"
                                value={settings.s3_acl}
                                onChange={handleInputChange}
                            >
                                <FormControlLabel value="private" control={<Radio/>} label="Private"/>
                                <FormControlLabel value="public-read" control={<Radio/>} label="Public Read"/>
                                <FormControlLabel value="public-read-write" control={<Radio/>}
                                                  label="Public Read-Write"/>
                            </RadioGroup>
                            <FormHelperText>{t('s3ACLDescription')}</FormHelperText>
                            <Typography variant="caption">{t('s3ACLExample')}</Typography>
                        </FormControl>
                    </Grid>


                    <Grid container spacing={2} alignItems="center">
                        <Grid item xs={12} sm={6}>
                            <TextField
                                label={t('s3PathLabel')}
                                name="s3_path"
                                value={settings.s3_path}
                                onChange={handleInputChange}
                                fullWidth
                                margin="normal"
                            />
                        </Grid>
                        <Grid item xs={12} sm={6}>
                            <Box ml={1}>
                                <Typography variant="body2" color="textSecondary">
                                    {t('s3PathDescription')}
                                </Typography>
                                <Typography variant="caption">
                                    {t('s3PathExample')}
                                </Typography>
                            </Box>
                        </Grid>
                    </Grid>


                    <Grid container spacing={2} alignItems="center">
                        <Grid item xs={12} sm={6}>
                            <TextField
                                label={t('s3PublicUrlLabel')}
                                name="s3_url"
                                value={settings.s3_url}
                                onChange={handleInputChange}
                                fullWidth
                                margin="normal"
                            />
                        </Grid>
                        <Grid item xs={12} sm={6}>
                            <Box ml={1}>
                                <Typography variant="body2" color="textSecondary">
                                    {t('s3PublicUrlDescription')}
                                </Typography>
                                <Typography variant="caption">
                                    {t('s3PublicUrlExample')}
                                </Typography>
                            </Box>
                        </Grid>
                    </Grid>

                    {/* s3_location 字段 (Radio) */}
                    <Grid item xs={12} sm={6}>
                        <FormControl component="fieldset" fullWidth margin="normal">
                            <FormLabel component="legend">{t('defaultLocationLabel')}</FormLabel>
                            <RadioGroup
                                row // 设置为 row，保证 radio 按钮在一行显示
                                name="s3_location"
                                value={settings.s3_location}
                                onChange={handleInputChange}
                            >
                                <FormControlLabel value="local" control={<Radio/>} label={t('local')}/>
                                <FormControlLabel value="s3" control={<Radio/>} label={t('s3')}/>
                                <FormControlLabel value="both" control={<Radio/>} label={t('localAndS3Option')}/>
                            </RadioGroup>
                        </FormControl>
                    </Grid>

                    <Grid item xs={12} sm={6}>
                        <Box ml={1}>
                            <Typography variant="body2" color="textSecondary">
                                {t('defaultLocationDescription')}
                                <ul>
                                    <li><b>{t('localOnly')}</b></li>
                                    <li><b>{t('s3Only')}</b></li>
                                    <li><b>{t('localAndS3')}</b></li>
                                </ul>
                            </Typography>
                            <Typography variant="caption">
                                {t('defaultLocationExample')}
                            </Typography>
                        </Box>
                    </Grid>


                    <Button variant="contained" color="primary" type="submit" fullWidth
                            disabled={loading || isRequesting}>
                        {loading ? t('saving') : t('save')}
                    </Button>
                </form>
            </FormWrapper>
            <ToastContainer
                position="top-right"
                autoClose={5000}
                hideProgressBar={false}
                newestOnTop={true}
                closeButton={true}
                rtl={false}
            />
        </Container>
    );
};

export default Setting;